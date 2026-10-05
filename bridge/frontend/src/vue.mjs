import ts from 'typescript'
import * as dom from '@vue/compiler-dom'
import { TypeScriptWriter } from './typescript.mjs'

/** A Vue file's SFC parse: its blocks, and the offset range of each script block's content. */
export class Sfc {
    constructor(source) {
        this.source = source
        this.errors = 0
        this.root = dom.parse(source.text, { parseMode: 'sfc', comments: true, onError: () => this.errors++, onWarn: () => {} })
        this.blocks = this.root.children.filter((child) => child.type === dom.NodeTypes.ELEMENT)
        this.scripts = this.blocks.filter((block) => block.tag === 'script' && !hasAttribute(block, 'src')).map(inner)
    }

    /** The text TypeScript checks: the file with everything outside its script blocks blanked, newlines kept. */
    checkedText() {
        const text = this.source.text
        let out = ''
        let at = 0
        for (const [start, end] of this.scripts) {
            out += blank(text.slice(at, start)) + text.slice(start, end)
            at = end
        }

        return out + blank(text.slice(at))
    }
}

/** Writes a Vue file as the contract's Component, its scripts as TypeScript from the whole program. */
export class VueWriter {
    constructor(tree, sfc, program, found) {
        this.tree = tree
        this.sfc = sfc
        this.source = sfc.source
        this.program = program
        this.found = found
        this.sourceFile = program.sourceFile(sfc.source.path)
        this.scripts = new TypeScriptWriter(tree, this.sourceFile, 0, program)
        this.imports = importsOf(this.sourceFile)
        this.errors = sfc.errors + (this.sourceFile.parseDiagnostics?.length ?? 0)
    }

    file() {
        const out = { id: this.tree.number(0, this.source.text.length), kind: 'Component', role: 'markup', span: this.source.span(0, this.source.text.length) }
        const blocks = []
        for (const child of this.sfc.root.children) {
            if (child.type === dom.NodeTypes.COMMENT) this.comment(child)
            if (child.type === dom.NodeTypes.ELEMENT) blocks.push(this.block(child))
        }
        if (blocks.length) out.children = blocks

        return out
    }

    block(block) {
        const out = this.markup(block, 'Block', 'blocks', { name: block.tag })
        const children = block.props.map((prop) => this.prop(prop))
        if (block.tag === 'script' && !hasAttribute(block, 'src')) {
            const [start, end] = inner(block)
            children.push(this.scripts.part(start, end, 'children'))
        } else if (block.tag === 'template' && !hasAttribute(block, 'lang')) {
            children.push(...block.children.flatMap((child) => this.template(child)))
        }
        if (children.length) out.children = children

        return out
    }

    template(node) {
        switch (node.type) {
            case dom.NodeTypes.COMMENT:
                this.comment(node)
                return []
            case dom.NodeTypes.TEXT:
                return node.content.trim() === '' ? [] : [this.markup(node, 'Text', 'children')]
            case dom.NodeTypes.INTERPOLATION: {
                const out = this.markup(node, 'Interpolation', 'children')
                out.children = this.expression(node.content, 'value')
                return [out]
            }
            case dom.NodeTypes.ELEMENT: {
                const out = this.markup(node, 'Element', 'children', { name: node.tag })
                if (node.tagType === dom.ElementTypes.COMPONENT) out.flags = ['component']
                const resolves = this.component(node.tag)
                if (resolves) out.resolves = resolves
                const children = [...node.props.map((prop) => this.prop(prop)), ...node.children.flatMap((child) => this.template(child))]
                if (children.length) out.children = children
                return [out]
            }
        }

        return []
    }

    prop(prop) {
        if (prop.type === dom.NodeTypes.ATTRIBUTE) {
            const facts = { name: prop.name }
            if (prop.value) facts.value = prop.value.content
            return this.markup(prop, 'Attribute', 'attributes', facts)
        }
        const out = this.markup(prop, 'Directive', 'attributes', { name: prop.rawName })
        if (/^[:@#.]/.test(prop.rawName)) out.flags = ['shorthand']
        out.extras = { vue: { directive: { name: prop.name, modifiers: prop.modifiers.map((modifier) => modifier.content ?? modifier) } } }
        const children = []
        if (prop.arg) children.push(...(prop.arg.isStatic ? [this.argument(prop.arg)] : this.expression(prop.arg, 'arg')))
        if (prop.forParseResult) {
            const { source, value, key, index } = prop.forParseResult
            for (const alias of [value, key, index].filter(Boolean)) children.push(this.pattern(alias, 'alias'))
            children.push(...this.expression(source, 'iterable'))
        } else if (prop.exp && prop.name === 'slot') {
            children.push(this.pattern(prop.exp, 'value'))
        } else if (prop.exp) {
            children.push(...this.expression(prop.exp, 'value', prop.name === 'on'))
        }
        if (children.length) out.children = children

        return out
    }

    markup(node, kind, field, facts = {}) {
        const [start, end] = [node.loc.start.offset, node.loc.end.offset]
        const out = { id: this.tree.number(start, end), kind, role: 'markup', span: this.source.span(start, end), field }

        return Object.assign(out, facts)
    }

    argument(arg) {
        const [start, end] = [arg.loc.start.offset, arg.loc.end.offset]

        return { id: this.tree.number(start, end), kind: 'Identifier', role: 'other', span: this.source.span(start, end), field: 'arg', name: arg.content }
    }

    /** A template expression, parsed by TypeScript, its spans pointing into the .vue file. An event handler may be statements. */
    expression(simple, field, handler = false) {
        const start = simple.loc.start.offset
        const text = simple.loc.source
        const wrapped = ts.createSourceFile('expression.ts', `(${text})`, ts.ScriptTarget.ESNext, true)
        const statement = wrapped.statements[0]
        if (!wrapped.parseDiagnostics.length && wrapped.statements.length === 1 && ts.isExpressionStatement(statement)) {
            return [new TypeScriptWriter(this.tree, wrapped, start - 1).node(statement.expression.expression, field)]
        }
        const statements = ts.createSourceFile('handler.ts', text, ts.ScriptTarget.ESNext, true)
        this.errors += handler ? statements.parseDiagnostics.length : wrapped.parseDiagnostics.length
        const writer = new TypeScriptWriter(this.tree, statements, start)
        this.found.push(...scriptComments(statements, start))

        return statements.statements.map((each) => writer.node(each, field))
    }

    /** A `v-for` alias or a `v-slot` value: a binding pattern, parsed as an arrow function's parameter. */
    pattern(simple, field) {
        const start = simple.loc.start.offset
        const wrapped = ts.createSourceFile('pattern.ts', `(${simple.loc.source}) => 0`, ts.ScriptTarget.ESNext, true)
        this.errors += wrapped.parseDiagnostics.length
        const parameter = wrapped.statements[0]?.expression?.parameters?.[0]
        if (!parameter) return this.expression(simple, field)[0]
        const out = new TypeScriptWriter(this.tree, wrapped, start - 1).node(parameter.name, field)
        out.role = 'pattern'

        return out
    }

    comment(node) {
        this.found.push({ start: node.loc.start.offset, end: node.loc.end.offset, kind: 'markup' })
    }

    /** The file a component tag names through the script's imports: `<OrderRow>` and `<order-row>` for `import OrderRow`. */
    component(tag) {
        const specifier = this.imports.get(tag) ?? this.imports.get(pascal(tag))
        if (!specifier) return undefined

        return this.program.resolve(specifier, this.sourceFile.fileName)
    }
}

/** Every comment in `sourceFile`, found around every node; `offset` is where its text starts in the file. */
export function scriptComments(sourceFile, offset) {
    const found = new Map()
    const text = sourceFile.text
    const visit = (node) => {
        for (const range of [...(ts.getLeadingCommentRanges(text, node.pos) ?? []), ...(ts.getTrailingCommentRanges(text, node.end) ?? [])]) {
            const raw = text.slice(range.pos, range.end)
            found.set(range.pos, { start: offset + range.pos, end: offset + range.end, kind: raw.startsWith('/**') && raw !== '/**/' ? 'doc' : raw.startsWith('/*') ? 'block' : 'line' })
        }
        ts.forEachChild(node, visit)
    }
    visit(sourceFile)
    visit(sourceFile.endOfFileToken)

    return [...found.values()]
}

/** The local names a script's imports bind, each to the specifier it came from. */
function importsOf(sourceFile) {
    const imports = new Map()
    for (const statement of sourceFile.statements) {
        if (!ts.isImportDeclaration(statement) || !ts.isStringLiteral(statement.moduleSpecifier)) continue
        const clause = statement.importClause
        if (clause?.name) imports.set(clause.name.text, statement.moduleSpecifier.text)
        const bindings = clause?.namedBindings
        if (bindings && ts.isNamedImports(bindings)) {
            for (const element of bindings.elements) imports.set(element.name.text, statement.moduleSpecifier.text)
        }
    }

    return imports
}

function inner(block) {
    return block.innerLoc ? [block.innerLoc.start.offset, block.innerLoc.end.offset] : [block.loc.start.offset, block.loc.start.offset]
}

function hasAttribute(block, name) {
    return block.props.some((prop) => prop.type === dom.NodeTypes.ATTRIBUTE && prop.name === name)
}

function blank(text) {
    return text.replace(/[^\n]/g, ' ')
}

function pascal(tag) {
    return tag.replace(/(^|-)([a-z0-9])/g, (_, __, char) => char.toUpperCase())
}
