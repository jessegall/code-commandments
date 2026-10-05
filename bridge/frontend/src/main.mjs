import { existsSync, lstatSync, readdirSync, realpathSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { createInterface } from 'node:readline'
import ts from 'typescript'
import { Source } from './source.mjs'
import { Program } from './program.mjs'
import { Tree } from './tree.mjs'
import { TypeScriptWriter } from './typescript.mjs'
import { Sfc, VueWriter, scriptComments } from './vue.mjs'
import { once } from 'node:events'
import { lineOf } from './json.mjs'
import { TreeCache } from './cache.mjs'

const NAME = 'bridge/frontend'
const VERSION = '1'
const SKIPPED_FOLDERS = new Set(['vendor', 'node_modules', 'site-packages', '__pycache__'])
const LANGUAGES = { '.vue': 'vue', ...Object.fromEntries(['.ts', '.tsx', '.mts', '.cts', '.js', '.jsx', '.mjs', '.cjs'].map((extension) => [extension, 'typescript'])) }
/** The mark a file read only for context carries, where its record puts it. */
const CONTEXT = ',"context":true'
/** How large a line is gathered to be kept. */
const GATHERED = 16 << 20

/**
 * The stream for one request: `paths` are scanned, `write` are judged (every other file is context), `renames` rewrite paths,
 * and `contents` is the text drafted for a file, read in place of the disk's; a drafted file under a folder is read too.
 */
export async function stream({ paths, write = [], renames = [], contents = {}, cache }, emit) {
    const roots = paths.map((path) => realpathSync(resolve(path)))
    const judged = write.map((path) => realpathSync(resolve(path)))
    const drafted = Object.keys(contents).filter((path) => languageOf(path) && !existsSync(path) && roots.some((root) => path.startsWith(root + '/')))
    const files = [...new Set([...roots.flatMap(filesIn), ...drafted])].sort()
    const sources = new Map(files.map((path) => [path, Object.hasOwn(contents, path) ? new Source(path, contents[path]) : Source.read(path)]))
    const sfcs = new Map(files.filter((path) => path.endsWith('.vue')).map((path) => [path, new Sfc(sources.get(path))]))
    const scripts = new Map([...sfcs].map(([path, sfc]) => [path, sfc.checkedText()]))
    const program = new Program(sources, scripts, renames)
    const kept = cache ? new TreeCache(cache, program, new Map([...sources].map(([path, source]) => [path, source.text])), scripts) : undefined
    const language = sfcs.size ? 'vue' : 'typescript'
    const totals = { expressions: 0, typed: 0, calls: 0, resolved: 0 }
    await emit({ header: { contract: 'tree', version: 1, language, bridge: { name: NAME, version: VERSION }, roots: roots.map((root) => program.shown(root)) } })
    for (const path of files) {
        const context = judged.length > 0 && !judged.some((each) => path === each || path.startsWith(each + '/'))
        const earlier = kept?.kept(path)
        if (earlier) {
            await emit(earlier.line.slice(0, earlier.at) + (context ? CONTEXT : '') + earlier.line.slice(earlier.at))
            for (const key of Object.keys(totals)) totals[key] += earlier.totals[key]
            continue
        }
        const tree = new Tree(sources.get(path))
        const { root, found, errors } = sfcs.has(path) ? vueFile(tree, sfcs.get(path), program) : typeScriptFile(tree, program)
        const file = { path: program.shown(path), language: languageOf(path), errors }
        const at = JSON.stringify({ file }).length - 2
        if (context) file.context = true
        file.resolver = { tool: 'tsc', ran: tree.checked }
        file.root = root
        file.comments = tree.comments(found)
        if (tree.types.length) file.types = tree.types
        const written = kept ? { pieces: [], size: 0 } : undefined
        await emit({ file }, written)
        const counts = Object.fromEntries(Object.keys(totals).map((key) => [key, tree[key]]))
        for (const key of Object.keys(totals)) totals[key] += counts[key]
        if (!written || written.pieces === undefined) continue
        const line = written.pieces.join('').slice(0, -1)
        kept.keep(path, context ? line.slice(0, at) + line.slice(at + CONTEXT.length) : line, at, counts)
    }
    if (program.aliases.length) await emit({ program: { aliases: program.aliases.map(({ prefix, path }) => ({ prefix, path: program.shown(path) })) } })
    await emit({ trailer: { files: files.length, resolution: { ...totals, unjoined: 0 } } })
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

/** Every file under `path` the bridge reads: `.vue` and `.ts`, skipping links, dot folders and dependency folders. */
function filesIn(path) {
    const stat = lstatSync(path)
    if (stat.isFile()) return languageOf(path) ? [path] : []
    if (!stat.isDirectory()) return []

    return readdirSync(path, { withFileTypes: true }).flatMap((entry) => {
        const child = join(path, entry.name)
        if (entry.isDirectory()) return entry.name.startsWith('.') || SKIPPED_FOLDERS.has(entry.name) || entry.name.endsWith('.egg-info') ? [] : filesIn(child)

        return entry.isFile() && languageOf(child) ? [child] : []
    })
}

function languageOf(path) {
    const extension = path.slice(path.lastIndexOf('.'))

    return LANGUAGES[extension]
}

function parse(argv) {
    const request = { paths: [], write: [], renames: [], serve: false, cache: undefined }
    for (const argument of argv) {
        if (argument === '--serve') request.serve = true
        else if (argument.startsWith('--cache=')) request.cache = resolve(argument.slice('--cache='.length))
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
    const request = parse(process.argv.slice(2))
    // A line is written a piece at a time, waiting for the reader to take each: a reader parsing as it reads would
    // otherwise leave the whole stream queued in this process's memory.
    const write = async (line, written) => {
        for (const piece of typeof line === 'string' ? [line + '\n'] : lineOf(line)) {
            gather(written, piece)
            if (!process.stdout.write(piece)) await once(process.stdout, 'drain')
        }
    }
    if (!request.serve) {
        if (!request.paths.length) throw new Error(`usage: node ${NAME} [--write=PATH]... [--rename=FROM=TO]... PATH...`)
        await stream(request, write)
        return
    }
    const lines = createInterface({ input: process.stdin })
    let answering = Promise.resolve()
    lines.on('line', (line) => {
        if (!line.trim()) return
        const asked = JSON.parse(line)
        answering = answering.then(() => stream({ paths: asked.paths ?? [], write: asked.write ?? [], renames: request.renames, contents: asked.contents ?? {}, cache: request.cache }, write)).catch(fail)
    })
}

/** Adds `piece` to what is gathered of a line to be kept, giving up on a line too large to keep. */
function gather(written, piece) {
    if (!written?.pieces) return
    written.size += piece.length
    if (written.size > GATHERED) written.pieces = undefined
    else written.pieces.push(piece)
}

function fail(error) {
    process.stderr.write(`${NAME}: ${error.stack ?? error}\n`)
    process.exit(1)
}

main().catch(fail)
