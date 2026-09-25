import ts from 'typescript'
import { existsSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { aliasesOf, projectRoot } from './aliases.mjs'
import { Source } from './source.mjs'
import { Sfc } from './vue.mjs'

/** Vue's own declarations, shipped beside the bundle: what a bare import resolves to when the project has none installed. */
const SHIPPED = resolve(dirname(fileURLToPath(import.meta.url)), 'types')

/** The suffix that turns a `.vue` file into the TypeScript file its scripts are checked as. */
const VIRTUAL = '.ts'

const DEFAULTS = {
    strict: true,
    target: ts.ScriptTarget.ESNext,
    module: ts.ModuleKind.ESNext,
    moduleResolution: ts.ModuleResolutionKind.Bundler,
    allowJs: true,
    jsx: ts.JsxEmit.Preserve,
    skipLibCheck: true,
    resolveJsonModule: true,
}

/**
 * One TypeScript program over every scanned file. A `.vue` file is checked as `<file>.vue.ts`: the file's own text
 * with everything outside its script blocks blanked, so every offset in it is an offset in the `.vue` file and
 * `import X from './X.vue'` resolves to it the way TypeScript resolves any extension.
 */
export class Program {
    /** `sources` maps each absolute path to its Source; `scripts` maps a `.vue` path to its checked text. */
    constructor(sources, scripts, renames) {
        this.sources = sources
        this.renames = renames
        this.virtual = new Map([...scripts].map(([path, text]) => [path + VIRTUAL, text]))
        const root = projectRoot(commonRoot([...sources.keys()]))
        this.aliases = aliasesOf(root)
        this.options = this.optionsFor(root)
        this.host = this.hostFor()
        const names = [...sources.keys()].map((path) => (scripts.has(path) ? path + VIRTUAL : path))
        this.program = ts.createProgram(names, this.options, this.host)
        this.checker = this.program.getTypeChecker()
    }

    /** The source file TypeScript checked for a scanned path. */
    sourceFile(path) {
        return this.program.getSourceFile(this.virtual.has(path + VIRTUAL) ? path + VIRTUAL : path)
    }

    /** The absolute file an import specifier reaches from `fileName`, as the stream names it. */
    resolve(specifier, fileName) {
        const found = ts.resolveModuleName(specifier, fileName, this.options, this.host).resolvedModule
        if (!found) return undefined

        return this.shown(resolve(found.resolvedFileName))
    }

    /** A file's name as the stream writes it: its real path, renamed. */
    shown(fileName) {
        const real = this.virtual.has(fileName) ? fileName.slice(0, -VIRTUAL.length) : fileName
        for (const [from, to] of this.renames) {
            if (real === from || real.startsWith(from + '/')) return to + real.slice(from.length)
        }

        return real
    }

    isLibrary(sourceFile) {
        return this.program.isSourceFileDefaultLibrary(sourceFile)
    }

    optionsFor(root) {
        const options = { ...DEFAULTS, ...configured(root) }
        const paths = { ...(options.paths ?? {}) }
        for (const { prefix, path } of this.aliases) {
            const key = prefix.endsWith('/') ? prefix + '*' : prefix + '/*'
            paths[key] ??= [path + '/*']
            paths[prefix.replace(/\/$/, '')] ??= [path]
        }
        options.paths = paths
        options.noEmit = true
        options.allowNonTsExtensions = true

        return options
    }

    /**
     * The checked text of a component outside the scan that an import reaches: read from disk as a scanned one is
     * read, so `import X from '../X.vue'` resolves wherever X lives, as an import of a `.ts` file does.
     */
    unscanned(name) {
        if (!name.endsWith('.vue' + VIRTUAL)) return undefined
        const path = name.slice(0, -VIRTUAL.length)
        if (!existsSync(path)) return undefined
        const checked = new Sfc(Source.read(path)).checkedText()
        this.virtual.set(name, checked)

        return checked
    }

    hostFor() {
        const host = ts.createCompilerHost(this.options, true)
        const { fileExists, readFile, getSourceFile } = host
        const text = (name) => this.virtual.get(name) ?? this.unscanned(name) ?? (name.endsWith('.vue') ? undefined : this.sources.get(name)?.text)
        host.fileExists = (name) => text(name) !== undefined || fileExists.call(host, name)
        host.readFile = (name) => text(name) ?? readFile.call(host, name)
        host.getSourceFile = (name, version, onError, create) => {
            const known = text(name)
            if (known === undefined) return getSourceFile.call(host, name, version, onError, create)

            return ts.createSourceFile(name, known, version, true, this.virtual.has(name) ? ts.ScriptKind.TS : undefined)
        }

        if (existsSync(SHIPPED)) {
            host.resolveModuleNameLiterals = (literals, containingFile, redirected, options) => literals.map((literal) => {
                const found = ts.resolveModuleName(literal.text, containingFile, options, host, undefined, redirected)
                if (found.resolvedModule || literal.text.startsWith('.')) return found

                return ts.resolveModuleName(literal.text, resolve(SHIPPED, 'index.ts'), options, host, undefined, redirected)
            })
        }

        return host
    }
}


/** The compiler options the nearest tsconfig.json above `root` sets, its referenced projects' paths merged in. */
function configured(root) {
    const found = ts.findConfigFile(root, ts.sys.fileExists)
    if (!found) return {}
    const options = parsed(found)
    if (options.paths) return options
    const references = ts.readConfigFile(found, ts.sys.readFile).config?.references ?? []
    for (const reference of references) {
        let path = resolve(dirname(found), reference.path)
        if (!path.endsWith('.json')) path = resolve(path, 'tsconfig.json')
        if (!ts.sys.fileExists(path)) continue
        const referenced = parsed(path)
        if (referenced.paths) return { ...options, paths: referenced.paths, baseUrl: referenced.baseUrl ?? options.baseUrl, pathsBasePath: referenced.pathsBasePath }
    }

    return options
}

function parsed(path) {
    const read = ts.readConfigFile(path, ts.sys.readFile)
    if (read.error) return {}

    return ts.parseJsonConfigFileContent(read.config, ts.sys, dirname(path), undefined, path).options
}

function commonRoot(paths) {
    if (!paths.length) return process.cwd()
    let common = dirname(paths[0])
    for (const path of paths) {
        while (common !== '/' && !(path === common || path.startsWith(common + '/'))) common = dirname(common)
    }

    return common
}
