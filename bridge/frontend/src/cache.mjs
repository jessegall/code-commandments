import { createHash } from 'node:crypto'
import { closeSync, existsSync, mkdirSync, openSync, readFileSync, readSync, renameSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import ts from 'typescript'

/** The lockfiles whose contents stand for what `node_modules` holds. */
const LOCKFILES = ['package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lock', 'bun.lockb']

/** How large a file's line may be and still be kept: a generated file's tree is not worth the disk. */
const KEPT = 16 << 20

/** How far into an entry its own first line is read, to answer whether the tree below it is still the file's. */
const META = 1 << 12

/**
 * A file's line as an earlier run wrote it, kept under a key of everything the line was made from: the bridge itself,
 * the compiler options, the renames, the lockfile, every declaration that reaches all files, and the file's own text
 * with the text of every file it imports, however far. A file whose key is unchanged is written from what was kept,
 * and the program is built over the files that are not.
 *
 * What the key leaves out is which other files the run scanned. A file's tree is made from its own text, what it
 * imports and what reaches every file, and nothing else — which is only true because a tree no longer answers for
 * the program's whole root set. Holding the scan in the key meant one file added or deleted anywhere renewed every
 * key and orphaned its predecessor, so a project gaining a file paid to read all of it again.
 */
export class TreeCache {
    /** `folder` holds the lines; `program` reads the options and resolves imports; `texts` maps each path to its text. */
    constructor(folder, program, texts, scripts) {
        this.folder = folder
        mkdirSync(folder, { recursive: true })
        this.keys = keysOf(program, texts, scripts, contextOf(program, texts))
        this.read = new Map()
        this.reaching = new Set([...texts].filter(([path, text]) => reachesEveryFile(path, text)).map(([path]) => path))
    }

    /**
     * Whether the file's declarations can reach a file that never imports it. Such a file belongs to the program
     * however fresh its own line is: TypeScript loads a declaration file nothing imports only because it is one of
     * the files the program was built over, and a global it declares is unresolved in every other file without it.
     */
    reaches(path) {
        return this.reaching.has(path)
    }

    /** Whether a line is kept for `path` under its key now, read from the entry's own first line alone. */
    fresh(path) {
        return this.metaOf(path) !== undefined
    }

    /** The line kept for `path` under its key now, with where a file's `context` mark goes and its counts; none when stale. */
    kept(path) {
        const meta = this.metaOf(path)
        if (!meta) return undefined
        const stored = readFileSync(this.entryOf(path), 'utf8')

        return { line: stored.slice(stored.indexOf('\n') + 1), at: meta.at, totals: meta.totals }
    }

    /** The entry's own first line, read without its tree, when it was written under the key `path` has now. */
    metaOf(path) {
        if (!this.read.has(path)) this.read.set(path, this.firstLineOf(path))
        const first = this.read.get(path)
        if (first === undefined) return undefined
        const meta = JSON.parse(first)

        return meta.key === this.keys.get(path) ? meta : undefined
    }

    /** The first line of the entry kept for `path`, read on its own; none when no entry is kept or it holds no line. */
    firstLineOf(path) {
        const entry = this.entryOf(path)
        if (!existsSync(entry)) return undefined
        const file = openSync(entry, 'r')
        try {
            const buffer = Buffer.alloc(META)
            const read = readSync(file, buffer, 0, META, 0)
            const first = buffer.subarray(0, read).indexOf(0x0a)

            return first === -1 ? undefined : buffer.subarray(0, first).toString('utf8')
        } finally {
            closeSync(file)
        }
    }

    /** Keeps `line` for `path`, `at` where its `context` mark goes, with the file's counts, unless the line is too large. */
    keep(path, line, at, totals) {
        if (line.length > KEPT) return
        this.read.delete(path)
        const entry = this.entryOf(path)
        const draft = `${entry}.${process.pid}`
        writeFileSync(draft, JSON.stringify({ key: this.keys.get(path), at, totals }) + '\n' + line)
        renameSync(draft, entry)
    }

    entryOf(path) {
        return join(this.folder, hashed(path) + '.tree')
    }
}

/** What every file's line is made from beside its own imports: the bridge, the options, the dependencies. */
function contextOf(program, texts) {
    const parts = [hashed(readFileSync(fileURLToPath(import.meta.url))), JSON.stringify(program.options), JSON.stringify(program.renames)]
    for (const name of LOCKFILES) {
        const lockfile = join(program.root, name)
        if (existsSync(lockfile)) parts.push(name, hashed(readFileSync(lockfile)))
    }
    for (const [path, text] of [...texts].sort(([a], [b]) => (a < b ? -1 : 1))) {
        if (reachesEveryFile(path, text)) parts.push(path, hashed(text))
    }

    return hashed(parts.join('\0'))
}

/**
 * Whether a file's declarations can reach a file that never imports it: a declaration file, or one that declares a
 * global or augments a module. A file this cannot rule out counts, so a change to it renews every key.
 */
function reachesEveryFile(path, text) {
    return path.endsWith('.d.ts') || text.includes('declare global') || text.includes('declare module')
}

/** Each file's key: the context, and the hash of the component of the import graph it sits in, with all it reaches. */
function keysOf(program, texts, scripts, context) {
    const edges = new Map()
    const own = new Map()
    for (const [path, text] of texts) {
        const imported = ts.preProcessFile(scripts.get(path) ?? text, true, true).importedFiles.map((each) => each.fileName)
        const reached = []
        const outside = []
        for (const specifier of imported) {
            const resolved = program.resolveAbsolute(specifier, path)
            if (!resolved) continue
            if (texts.has(resolved)) reached.push(resolved)
            else if (!resolved.includes('/node_modules/')) outside.push(resolved, existsSync(resolved) ? hashed(readFileSync(resolved)) : '')
        }
        edges.set(path, [...new Set(reached)].sort())
        own.set(path, hashed([path, text, ...outside].join('\0')))
    }
    const deep = componentHashes(edges, own)

    return new Map([...texts.keys()].map((path) => [path, hashed(context + '\0' + deep.get(path))]))
}

/**
 * The hash of everything a file reaches through the import graph, its strongly connected component hashed as one:
 * the members' own hashes and the hashes of the components it imports. Tarjan's algorithm, iterative.
 */
function componentHashes(edges, own) {
    const index = new Map()
    const low = new Map()
    const onStack = new Set()
    const stack = []
    const component = new Map()
    const hashOf = new Map()
    let counter = 0
    for (const start of edges.keys()) {
        if (index.has(start)) continue
        const work = [[start, 0]]
        while (work.length) {
            const frame = work[work.length - 1]
            const [node, at] = frame
            if (at === 0) {
                index.set(node, counter)
                low.set(node, counter)
                counter++
                stack.push(node)
                onStack.add(node)
            }
            const next = edges.get(node)[at]
            if (next !== undefined) {
                frame[1] = at + 1
                if (!index.has(next)) work.push([next, 0])
                else if (onStack.has(next)) low.set(node, Math.min(low.get(node), index.get(next)))
                continue
            }
            work.pop()
            if (work.length) {
                const parent = work[work.length - 1][0]
                low.set(parent, Math.min(low.get(parent), low.get(node)))
            }
            if (low.get(node) !== index.get(node)) continue
            const members = []
            let member
            do {
                member = stack.pop()
                onStack.delete(member)
                members.push(member)
            } while (member !== node)
            const id = members.slice().sort()[0]
            for (const each of members) component.set(each, id)
            const reached = new Set()
            for (const each of members) {
                for (const target of edges.get(each)) {
                    if (component.get(target) !== id) reached.add(hashOf.get(component.get(target)))
                }
            }
            hashOf.set(id, hashed([...members.map((each) => own.get(each)).sort(), '|', ...[...reached].sort()].join('\0')))
        }
    }

    return new Map([...edges.keys()].map((path) => [path, hashOf.get(component.get(path))]))
}

function hashed(text) {
    return createHash('sha256').update(text).digest('hex')
}
