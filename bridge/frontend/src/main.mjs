import { existsSync, lstatSync, readFileSync, realpathSync } from 'node:fs'
import { basename, dirname, join, resolve } from 'node:path'
import { createInterface } from 'node:readline'
import ts from 'typescript'
import { declarations } from './declares.mjs'
import { Source } from './source.mjs'
import { Program } from './program.mjs'
import { Tree } from './tree.mjs'
import { TypeScriptWriter } from './typescript.mjs'
import { Sfc, VueWriter, scriptComments } from './vue.mjs'
import { once } from 'node:events'
import { lineOf } from './json.mjs'

const NAME = 'bridge/frontend'
const VERSION = '1'
const LANGUAGES = { '.vue': 'vue', ...Object.fromEntries(['.ts', '.tsx', '.mts', '.cts', '.js', '.jsx', '.mjs', '.cjs'].map((extension) => [extension, 'typescript'])) }
/** The files beside the sources that shape every tree: what is installed and how TypeScript reads the project. */
const PINS = ['package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lock', 'bun.lockb', 'tsconfig.json', 'jsconfig.json']

/** Whether $path is one of the judged paths or lies in a judged folder: the path and each folder above it looked up once. */
function judges(judged, path) {
    for (let held = path; ; held = dirname(held)) {
        if (judged.has(held)) return true
        if (dirname(held) === held) return false
    }
}

/**
 * The stream for one request: `paths` are scanned, `write` are written (every scanned file when it names none), each
 * line naming the files its tree read; `renames` rewrite paths, and `contents` is the text drafted for a file, read in
 * place of the disk's; a drafted file under a folder is read too. The bridge keeps nothing between requests.
 */
export async function stream({ paths, write = [], renames = [], contents = {} }, emit) {
    const roots = paths.map(named)
    const judged = new Set(write.map((path) => realpathSync(resolve(path))))
    const drafted = Object.keys(contents).filter((path) => languageOf(path) && !existsSync(path) && roots.includes(path))
    const files = [...new Set([...roots.flatMap(filesIn), ...drafted])].sort()
    const sources = new Map(files.map((path) => [path, Object.hasOwn(contents, path) ? new Source(path, contents[path]) : Source.read(path)]))
    const sfcs = new Map()
    const sfcOf = (path) => {
        if (!sfcs.has(path)) sfcs.set(path, new Sfc(sources.get(path)))

        return sfcs.get(path)
    }
    const scripts = new Map(files.filter((path) => path.endsWith('.vue')).map((path) => [path, sfcOf(path).checkedText()]))
    const asked = judged.size ? files.filter((path) => judges(judged, path)) : files
    const everywhere = files.filter((path) => reachesEveryFile(path, sources.get(path).text))
    const program = new Program(sources, scripts, renames, [...new Set([...asked, ...everywhere])])
    const readBy = readsOf(program, sources, scripts, everywhere)
    const language = scripts.size ? 'vue' : 'typescript'
    const totals = { expressions: 0, typed: 0, calls: 0, resolved: 0 }
    await emit({ header: { contract: 'tree', version: 1, language, bridge: { name: NAME, version: VERSION, reads: true }, roots: roots.map((root) => program.shown(root)) } })
    for (const path of asked) {
        const tree = new Tree(sources.get(path))
        const { root, found, errors } = scripts.has(path) ? vueFile(tree, sfcOf(path), program) : typeScriptFile(tree, program)
        const file = { path: program.shown(path), language: languageOf(path), errors }
        file.reads = readBy(path).map((read) => program.shown(read))
        file.resolver = { tool: 'tsc', ran: tree.checked }
        file.root = root
        file.comments = tree.comments(found)
        if (tree.types.length) file.types = tree.types
        await emit({ file })
        for (const key of Object.keys(totals)) totals[key] += tree[key]
    }
    if (program.aliases.length) await emit({ program: { aliases: program.aliases.map(({ prefix, path }) => ({ prefix, path: program.shown(path) })) } })
    await emit({ trailer: { files: asked.length, resolution: { ...totals, unjoined: 0 } } })
}

/**
 * Whether a file's declarations can reach a file that never imports it: a declaration file, or one that declares a
 * global or augments a module. Every tree reads such a file.
 */
function reachesEveryFile(path, text) {
    return path.endsWith('.d.ts') || text.includes('declare global') || text.includes('declare module')
}

/**
 * The files each tree read: every file of the project it imports, however deep, every file whose declarations reach
 * every file, and the files that pin what is installed and how TypeScript reads the project.
 */
function readsOf(program, sources, scripts, everywhere) {
    const imports = new Map()
    const importedBy = (path) => {
        if (imports.has(path)) return imports.get(path)
        const text = scripts.get(path) ?? sources.get(path)?.text ?? (existsSync(path) ? readFileSync(path, 'utf8') : '')
        const found = []
        for (const { fileName } of ts.preProcessFile(text, true, true).importedFiles) {
            const resolved = program.resolveAbsolute(fileName, path)
            if (resolved && !resolved.includes('/node_modules/')) found.push(resolved)
        }
        imports.set(path, found)

        return found
    }
    const pins = PINS.map((name) => join(program.root, name)).filter((pin) => existsSync(pin))

    return (path) => {
        const reached = new Set()
        const waiting = [path]
        while (waiting.length) {
            for (const next of importedBy(waiting.pop())) {
                if (!reached.has(next)) {
                    reached.add(next)
                    waiting.push(next)
                }
            }
        }
        for (const file of everywhere) reached.add(file)
        reached.delete(path)

        return [...new Set([...reached, ...pins])].sort()
    }
}

function typeScriptFile(tree, program) {
    const sourceFile = program.sourceFile(tree.source.path)
    const root = new TypeScriptWriter(tree, sourceFile, 0, program).node(sourceFile, undefined)
    root.span = tree.source.span(0, tree.source.text.length)
    tree.spans[0].start = 0

    return { root, found: scriptComments(sourceFile, 0), errors: sourceFile.parseDiagnostics?.length ?? 0 }
}

function vueFile(tree, sfc, program) {
    const found = []
    const writer = new VueWriter(tree, sfc, program, found)
    const root = writer.file()
    found.push(...scriptComments(writer.sourceFile, 0))

    return { root, found, errors: writer.errors }
}

/** The file `path` names, when it is one the bridge reads: `.vue` or `.ts`, and not a link. The bridge walks no
 * folder: the Go side lists every file through its one funnel, so a folder named here is refused. */
function filesIn(path) {
    const stat = lstatSync(path, { throwIfNoEntry: false })
    if (stat?.isDirectory()) throw new Error(`the frontend bridge reads only the files it is handed; ${path} is a folder`)

    return stat?.isFile() && languageOf(path) ? [path] : []
}

/** The path itself, made absolute with its folder resolved: a link it names stays a link, so `filesIn` sees it. */
function named(path) {
    const absolute = resolve(path)
    const folder = dirname(absolute)

    return existsSync(folder) ? join(realpathSync(folder), basename(absolute)) : absolute
}

function languageOf(path) {
    const extension = path.slice(path.lastIndexOf('.'))

    return LANGUAGES[extension]
}

function parse(argv) {
    const request = { paths: [], write: [], renames: [], serve: false }
    for (const argument of argv) {
        if (argument === '--serve') request.serve = true
        else if (argument.startsWith('--write=')) request.write.push(argument.slice('--write='.length))
        else if (argument.startsWith('--rename=')) {
            const [from, to] = argument.slice('--rename='.length).split('=')
            request.renames.push([realpathSync(resolve(from)), to])
        } else if (argument.startsWith('--')) throw new Error(`unknown flag ${argument}`)
        else request.paths.push(argument)
    }

    return request
}

async function main() {
    // The one ask besides a tree: what this bridge can emit, one declaration per language it writes.
    if (process.argv.slice(2).includes('--declares')) {
        for (const declared of declarations()) process.stdout.write(JSON.stringify(declared) + '\n')
        return
    }
    const request = parse(process.argv.slice(2))
    // A line is written a piece at a time, waiting for the reader to take each: a reader parsing as it reads would
    // otherwise leave the whole stream queued in this process's memory.
    const write = async (line) => {
        for (const piece of typeof line === 'string' ? [line + '\n'] : lineOf(line)) {
            if (!process.stdout.write(piece)) await once(process.stdout, 'drain')
        }
    }
    if (!request.serve) {
        if (!request.paths.length) throw new Error(`usage: node ${NAME} [--write=PATH]... [--rename=FROM=TO]... PATH...`)
        await stream(request, write)
        return
    }
    leavesWithItsParent()
    const lines = createInterface({ input: process.stdin })
    let answering = Promise.resolve()
    lines.on('line', (line) => {
        if (!line.trim()) return
        const asked = JSON.parse(line)
        answering = answering.then(() => stream({ paths: asked.paths ?? [], write: asked.write ?? [], renames: request.renames, contents: asked.contents ?? {} }, write)).catch(fail)
    })
}

/**
 * Ends this bridge once the run that started it is gone. A run killed outright does not always close our input, and a
 * bridge left like that would answer no one: once a second it asks whether the process that started it still exists
 * (node reads its parent's id once, at start), and ends when it does not.
 */
function leavesWithItsParent() {
    const parent = process.ppid
    setInterval(() => {
        try {
            process.kill(parent, 0)
        } catch {
            process.exit(0)
        }
    }, 1000).unref()
}

function fail(error) {
    process.stderr.write(`${NAME}: ${error.stack ?? error}\n`)
    process.exit(1)
}

main().catch(fail)
