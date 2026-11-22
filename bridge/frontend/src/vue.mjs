import ts from 'typescript'
import * as dom from '@vue/compiler-dom'
import { Node } from './tree.mjs'
import { Translator } from './typescript.mjs'

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
        return checkedTextOf(this.source.text, this.scripts)
    }
}

/** `text` with everything outside the `[start, end)` script ranges blanked, newlines kept: what TypeScript checks of a Vue file. */
export function checkedTextOf(text, scripts) {
    let out = ''
    let at = 0
    for (const [start, end] of scripts) {
        out += blank(text.slice(at, start)) + text.slice(start, end)
        at = end
    }

    return out + blank(text.slice(at))
}

/** Writes a Vue file as the version 5 tree: a module per script block, its code translated from the whole program, and a module named `template` for the markup. */
export class VueWriter {
    constructor(tree, sfc, program, found) {
        this.tree = tree
        this.sfc = sfc
        this.source = sfc.source
        this.program = program
        this.found = found
        this.sourceFile = program.sourceFile(sfc.source.path)
        this.scripts = new Translator(tree, this.sourceFile, 0, program)
        this.imports = importsOf(this.sourceFile)
        this.errors = sfc.errors + (this.sourceFile.parseDiagnostics?.length ?? 0)
    }

    file() {
        const blocks = this.sfc.root.children
        const scripts = new Map(blocks.filter((child) => child.type === dom.NodeTypes.ELEMENT && child.tag === 'script' && !hasAttribute(child, 'src'))
            .map((child) => [child, this.script(child)]))
        this.scope = this.setupScope()
        const modules = []
        for (const child of blocks) {
            if (child.type === dom.NodeTypes.COMMENT) this.comment(child)
            if (child.type !== dom.NodeTypes.ELEMENT) continue
            if (scripts.has(child)) modules.push(scripts.get(child))
            if (child.tag === 'template' && !hasAttribute(child, 'lang')) modules.push(this.markup(child))
        }

        return new Node('file', 0, this.source.text.length).holdsAll('modules', modules.filter(Boolean))
    }

    /** A translator for template code, which reads a name the script declares as that declaration. */
    templateTranslator(sourceFile, offset) {
        const translator = new Translator(this.tree, sourceFile, offset)
        translator.scope = this.scope

        return translator
    }

    /**
     * The names a template reads from the script, each to the node written for its declaration: every top-level
     * variable of the script, and every prop `defineProps<…>()` declares, by the compiler's reading of its type.
     */
    setupScope() {
        const scope = new Map()
        const bindings = this.tree.bindings ?? new Map()
        const declare = (name) => {
            if (name && bindings.has(name)) scope.set(name.text, bindings.get(name))
        }
        for (const statement of this.sourceFile.statements) {
            if (ts.isVariableStatement(statement)) statement.declarationList.declarations.forEach((declaration) => declare(declaration.name))
        }
        const checker = this.program?.checker
        const visit = (node) => {
            if (ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === 'defineProps' && node.typeArguments?.length && checker) {
                for (const property of checker.getTypeFromTypeNode(node.typeArguments[0]).getProperties()) {
                    for (const declaration of property.declarations ?? []) declare(declaration.name)
                }
            }
            ts.forEachChild(node, visit)
        }
        visit(this.sourceFile)

        return scope
    }

    /** A script block's module, spanning its first statement to its last. */
    script(block) {
        const [start, end] = inner(block)
        const statements = this.sourceFile.statements.filter((statement) => statement.getStart(this.sourceFile) >= start && statement.end <= end)
        if (!statements.length) return new Node('module', start, start)

        return this.scripts.module(statements, statements[0].getStart(this.sourceFile), statements.at(-1).end)
    }

    /** The template block's module, named `template`, spanning its first child to its last. */
    markup(block) {
        const children = block.children.flatMap((child) => this.template(child))
        if (!children.length) return undefined

        return new Node('module', children[0].start, children.at(-1).end).fact('name', 'template').holdsAll('statements', children)
    }

    template(node) {
        switch (node.type) {
            case dom.NodeTypes.COMMENT:
                this.comment(node)
                return []
            case dom.NodeTypes.TEXT:
                return node.content.trim() === '' ? [] : [this.at('text', node).said('value', node.content.replace(/\s+/g, ' ').trim())]
            case dom.NodeTypes.INTERPOLATION:
                return [this.at('embedded-expression', node).holds('expression', this.expression(node.content))]
            case dom.NodeTypes.ELEMENT: {
                const element = this.at('element', node)
                    .fact('tag', node.tag)
                    .fact('form', node.tagType === dom.ElementTypes.COMPONENT ? 'component' : 'native')
                    .fact('resolves', this.component(node.tag))
                element.holdsAll('attributes', node.props.map((prop) => this.prop(prop, element)))
                element.holdsAll('children', node.children.flatMap((child) => this.template(child)))
                linkTemplateScope(element)
                return [element]
            }
        }

        return []
    }

    prop(prop, element) {
        if (prop.type === dom.NodeTypes.ATTRIBUTE) {
            const value = prop.value && new Node('literal', prop.value.loc.start.offset + 1, prop.value.loc.end.offset - 1).fact('literalKind', 'string').said('value', prop.value.content)

            return this.at('attribute', prop).fact('name', prop.name).holds('value', value)
        }
        const directive = this.at('directive', prop)
            .fact('name', prop.name)
            .fact('modifiers', prop.modifiers.map((modifier) => modifier.content ?? modifier))
            .fact('shorthand', /^[:@#.]/.test(prop.rawName))
            .link('body', element)
        if (prop.arg) directive.holds('argument', prop.arg.isStatic ? this.at('reference', prop.arg).fact('name', prop.arg.content).fact('form', 'name') : this.expression(prop.arg))
        if (prop.forParseResult) {
            const { source, value, key, index } = prop.forParseResult
            directive.holdsAll('bindings', [value, key, index].filter(Boolean).map((alias) => this.pattern(alias)))
            directive.holds('iterable', this.expression(source))
        } else if (prop.exp && prop.name === 'slot') {
            directive.holds('pattern', this.pattern(prop.exp))
        } else if (prop.exp) {
            directive.holds('value', this.expression(prop.exp, prop.name === 'on'))
        }

        return directive
    }

    at(kind, node) {
        return new Node(kind, node.loc.start.offset, node.loc.end.offset)
    }

    /** A template expression, parsed by TypeScript, its spans pointing into the .vue file. An event handler may be statements. */
    expression(simple, handler = false) {
        const start = simple.loc.start.offset
        const text = simple.loc.source
        const wrapped = ts.createSourceFile('expression.ts', `(${text})`, ts.ScriptTarget.ESNext, true)
        const statement = wrapped.statements[0]
        if (!wrapped.parseDiagnostics.length && wrapped.statements.length === 1 && ts.isExpressionStatement(statement)) {
            return this.templateTranslator(wrapped, start - 1).expression(statement.expression.expression)
        }
        const statements = ts.createSourceFile('handler.ts', text, ts.ScriptTarget.ESNext, true)
        this.errors += handler ? statements.parseDiagnostics.length : wrapped.parseDiagnostics.length
        const translator = this.templateTranslator(statements, start)
        this.found.push(...scriptComments(statements, start))

        return new Node('block', start, start + text.length).holdsAll('statements', statements.statements.map((each) => translator.statement(each)))
    }

    /** A `v-for` alias or a `v-slot` value: a binding pattern, parsed as an arrow function's parameter. */
    pattern(simple) {
        const start = simple.loc.start.offset
        const wrapped = ts.createSourceFile('pattern.ts', `(${simple.loc.source}) => 0`, ts.ScriptTarget.ESNext, true)
        this.errors += wrapped.parseDiagnostics.length
        const parameter = wrapped.statements[0]?.expression?.parameters?.[0]
        if (!parameter) return this.expression(simple)

        return new Translator(this.tree, wrapped, start - 1).binding(parameter.name)
    }

    comment(node) {
        this.found.push({ start: node.loc.start.offset, end: node.loc.end.offset, form: 'markup' })
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
            found.set(range.pos, { start: offset + range.pos, end: offset + range.end, form: raw.startsWith('/**') && raw !== '/**/' ? 'doc' : raw.startsWith('/*') ? 'block' : 'line' })
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

/**
 * Links each read of a name a `v-for` or `v-slot` on the element binds, inside the element, to that binding: the
 * element's other attributes and its children, never the iterable the aliases come from. A read the script already
 * linked keeps its link.
 */
function linkTemplateScope(element) {
    const bound = new Map()
    const iterables = new Set()
    for (const directive of element.parts.attributes ?? []) {
        if (directive.kind !== 'directive') continue
        for (const binding of [...(directive.parts.bindings ?? []), directive.parts.pattern].filter(Boolean).flatMap(bindingsIn)) bound.set(binding.facts.name, binding)
        if (directive.parts.iterable) iterables.add(directive.parts.iterable)
    }
    if (!bound.size) return
    const visit = (node) => {
        if (iterables.has(node)) return
        if (node.kind === 'reference' && !node.links.binding && bound.has(node.facts.name)) node.link('binding', bound.get(node.facts.name))
        node.children().forEach(visit)
    }
    element.children().forEach(visit)
}

/** The binding nodes a binding or a destructuring pattern holds. */
function bindingsIn(node) {
    return node.kind === 'binding' ? [node] : node.children().flatMap(bindingsIn)
}
