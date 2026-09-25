import ts from 'typescript'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'

const VITE_CONFIGS = ['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.mts']
const ROOT_MARKERS = [...VITE_CONFIGS, 'package.json']

/** The project a folder belongs to: the nearest folder above it holding a vite config or a package.json. */
export function projectRoot(folder) {
    for (let at = folder; ; at = dirname(at)) {
        if (ROOT_MARKERS.some((marker) => existsSync(join(at, marker)))) return at
        if (dirname(at) === at) return folder
    }
}

/** The module path aliases the project's vite config declares, `[{prefix, path}]`, longest prefix first. */
export function aliasesOf(root) {
    const config = VITE_CONFIGS.map((name) => join(root, name)).find(existsSync)
    if (!config) return []
    const file = ts.createSourceFile(config, readFileSync(config, 'utf8'), ts.ScriptTarget.ESNext, true)
    const reader = new ConfigReader(file, dirname(config))
    const aliases = []
    for (const [prefix, target] of reader.entries()) {
        const path = reader.folder(target, [])
        if (path) aliases.push({ prefix, path })
    }

    return aliases.sort((a, b) => b.prefix.length - a.prefix.length)
}

/** Reads a vite config's `resolve.alias` statically: literals, `__dirname`, path joins, `new URL(…, import.meta.url)`. */
class ConfigReader {
    constructor(file, folder) {
        this.file = file
        this.root = folder
    }

    /** Each alias as `[prefix, the expression it maps to]`, from an object or a `[{find, replacement}]` array. */
    entries() {
        const alias = this.find((node) => ts.isPropertyAssignment(node) && propertyName(node.name) === 'alias')
        if (!alias) return []
        const value = this.value(alias.initializer)
        if (ts.isObjectLiteralExpression(value)) {
            return value.properties.filter(ts.isPropertyAssignment)
                .map((property) => [propertyName(property.name), property.initializer])
                .filter(([prefix]) => prefix !== undefined)
        }
        if (ts.isArrayLiteralExpression(value)) {
            return value.elements.filter(ts.isObjectLiteralExpression).flatMap((entry) => {
                const find = entry.properties.find((property) => ts.isPropertyAssignment(property) && propertyName(property.name) === 'find')
                const replacement = entry.properties.find((property) => ts.isPropertyAssignment(property) && propertyName(property.name) === 'replacement')
                if (!find || !replacement || !ts.isStringLiteralLike(find.initializer)) return []

                return [[find.initializer.text, replacement.initializer]]
            })
        }

        return []
    }

    /** The absolute folder an expression names, or undefined when it names none this reader can follow. */
    folder(node, seen) {
        node = unwrap(node)
        if (ts.isStringLiteralLike(node)) return node.text.startsWith('/') ? join(this.root, node.text) : resolve(this.root, node.text)
        if (ts.isIdentifier(node)) {
            if (node.text === '__dirname') return this.root
            if (seen.includes(node.text)) return undefined
            const declared = this.declared(node.text)

            return declared && this.folder(declared, [...seen, node.text])
        }
        if (ts.isNewExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === 'URL') {
            const [path, base] = node.arguments ?? []

            return path && base && isImportMetaUrl(base) && ts.isStringLiteralLike(path) ? resolve(this.root, path.text) : undefined
        }
        if (ts.isCallExpression(node)) return this.called(node, seen)

        return undefined
    }

    called(call, seen) {
        const callee = calleeName(call)
        const [first, ...rest] = call.arguments
        if (callee === 'fileURLToPath') return first && (isImportMetaUrl(first) ? this.root : this.folder(first, seen))
        if (callee === 'dirname') return first && this.folder(first, seen)
        if (callee !== 'resolve' && callee !== 'join') return undefined
        if (!first) return this.root
        const base = this.folder(first, seen) ?? (ts.isStringLiteralLike(first) ? undefined : this.root)
        const segments = rest.map((argument) => (ts.isStringLiteralLike(argument) ? argument.text : undefined))
        if (!base || segments.includes(undefined)) return undefined

        return resolve(base, ...segments)
    }

    /** The initializer of the module-level variable `name`. */
    declared(name) {
        const declaration = this.find((node) => ts.isVariableDeclaration(node) && ts.isIdentifier(node.name) && node.name.text === name)

        return declaration?.initializer
    }

    value(node) {
        node = unwrap(node)

        return ts.isIdentifier(node) ? (this.declared(node.text) ?? node) : node
    }

    find(wanted) {
        let found
        const visit = (node) => {
            if (found) return
            if (wanted(node)) found = node
            else ts.forEachChild(node, visit)
        }
        visit(this.file)

        return found
    }
}

function unwrap(node) {
    while (ts.isParenthesizedExpression(node) || ts.isAsExpression(node) || ts.isSatisfiesExpression(node)) node = node.expression

    return node
}

function propertyName(name) {
    return ts.isIdentifier(name) || ts.isStringLiteralLike(name) ? name.text : undefined
}

function calleeName(call) {
    if (ts.isIdentifier(call.expression)) return call.expression.text
    if (ts.isPropertyAccessExpression(call.expression)) return call.expression.name.text

    return undefined
}

function isImportMetaUrl(node) {
    return ts.isPropertyAccessExpression(node) && node.name.text === 'url' && ts.isMetaProperty(node.expression)
}
