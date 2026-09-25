// Writes one TypeScript or Vue file as a generic tree stream (contract/CONTRACT.md), the way the frontend bridge will.
// Usage: NODE_PATH=<node_modules> node frontend.mjs ts|vue <file> <path-in-stream>
import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const require = createRequire(process.env.NODE_PATH + '/')
const ts = require('typescript')
const dom = require('@vue/compiler-dom')

const [mode, file, shown] = process.argv.slice(2)
const source = readFileSync(file, 'utf8')

const KINDS = {}
for (const [name, value] of Object.entries(ts.SyntaxKind)) {
    if (typeof value === 'number' && !/^(First|Last)/.test(name) && !(value in KINDS)) KINDS[value] = name
}
const MEMBERS = new Set(['FunctionDeclaration', 'ClassDeclaration', 'InterfaceDeclaration', 'TypeAliasDeclaration', 'EnumDeclaration',
    'MethodDeclaration', 'PropertyDeclaration', 'PropertySignature', 'MethodSignature', 'Constructor', 'GetAccessor', 'SetAccessor', 'EnumMember'])
const SKIPPED_KEYS = new Set(['parent', 'pos', 'end', 'flags', 'kind', 'modifierFlagsCache', 'transformFlags', 'jsDoc', 'symbol', 'localSymbol',
    'locals', 'nextContainer', 'flowNode', 'emitNode', 'id', 'original', 'modifiers', 'questionToken', 'dotDotDotToken', 'asteriskToken',
    'exclamationToken', 'equalsGreaterThanToken', 'operatorToken', 'questionDotToken', 'endFlowNode', 'returnFlowNode', 'nextToken'])

/** Byte offset of every UTF-16 index in `text`. */
function byteTable(text) {
    const table = new Array(text.length + 1)
    let mark = 0
    for (let index = 0; index < text.length; index++) {
        table[index] = mark
        const code = text.charCodeAt(index)
        mark += code < 0x80 ? 1 : code < 0x800 ? 2 : code >= 0xd800 && code <= 0xdfff ? 2 : 3
    }
    table[text.length] = mark

    return table
}

const bytes = byteTable(source)
const lineAt = (offset) => source.slice(0, offset).split('\n').length

class Tree {
    constructor() {
        this.next = 0
        this.spans = []
        this.calls = 0
        this.resolved = 0
    }

    number(start, end) {
        const id = this.next++
        this.spans.push({ start: bytes[start], end: bytes[end], id })

        return id
    }
}

/** A TypeScript tree, read from `sourceFile`, whose text starts `base` characters into the file. */
class TypeScriptWriter {
    constructor(tree, sourceFile, checker, base) {
        Object.assign(this, { tree, sourceFile, checker, base })
    }

    node(node, field) {
        const start = this.base + node.getStart(this.sourceFile)
        const end = this.base + node.end
        const id = this.tree.number(start, end)
        const kind = KINDS[node.kind]
        const out = { id, kind, role: this.role(node, kind, field) }
        const is = this.neutral(node)
        if (is.length) out.is = is
        out.span = [bytes[start], bytes[end], lineAt(start)]
        if (field) out.field = field
        Object.assign(out, this.facts(node, kind))
        const children = []
        ts.forEachChild(node, (child) => {
            const slot = this.fieldOf(node, child)
            if (slot) children.push(this.node(child, slot))
        })
        if (children.length) out.children = children

        return out
    }

    /** The slot `child` fills in `parent`; none for a token, which the contract never makes a node. */
    fieldOf(parent, child) {
        for (const [key, value] of Object.entries(parent)) {
            if (value !== child && !(Array.isArray(value) && value.includes(child))) continue
            if (key === 'modifiers' && ts.isDecorator(child)) return key
            if (SKIPPED_KEYS.has(key)) return undefined

            return key
        }
        throw new Error(`${KINDS[child.kind]} fills no field of ${KINDS[parent.kind]}`)
    }

    role(node, kind, field) {
        if (MEMBERS.has(kind)) return 'member'
        if (ts.isTypeNode(node)) return 'type'
        if (ts.isObjectBindingPattern(node) || ts.isArrayBindingPattern(node)) return 'pattern'
        if (ts.isStatement(node) || ts.isImportDeclaration(node)) return 'statement'
        if (field === 'name' && ts.isIdentifier(node)) return 'other'
        if (ts.isExpression(node) || ts.isIdentifier(node)) return 'expression'

        return 'other'
    }

    neutral(node) {
        const answers = {
            function: ts.isFunctionLike(node) && node.body !== undefined,
            'type-declaration': ts.isClassDeclaration(node) || ts.isInterfaceDeclaration(node) || ts.isTypeAliasDeclaration(node) || ts.isEnumDeclaration(node),
            parameter: ts.isParameter(node),
            block: ts.isBlock(node),
            branch: ts.isIfStatement(node) || ts.isSwitchStatement(node) || ts.isConditionalExpression(node),
            loop: ts.isIterationStatement(node, false),
            return: ts.isReturnStatement(node),
            throw: ts.isThrowStatement(node),
            'bail-out': ts.isReturnStatement(node) || ts.isThrowStatement(node) || ts.isBreakOrContinueStatement(node),
            'expression-statement': ts.isExpressionStatement(node),
            call: ts.isCallExpression(node),
            construction: ts.isNewExpression(node),
            'member-access': ts.isPropertyAccessExpression(node) || ts.isElementAccessExpression(node),
            'null-safe': (ts.isPropertyAccessExpression(node) || ts.isCallExpression(node) || ts.isElementAccessExpression(node)) && node.questionDotToken !== undefined,
            'self-reference': node.kind === ts.SyntaxKind.ThisKeyword,
            identifier: ts.isIdentifier(node) && !ts.isDeclaration(node.parent) && ts.isExpression(node),
            assignment: ts.isBinaryExpression(node) && node.operatorToken.kind >= ts.SyntaxKind.FirstAssignment && node.operatorToken.kind <= ts.SyntaxKind.LastAssignment,
            comparison: ts.isBinaryExpression(node) && [ts.SyntaxKind.EqualsEqualsEqualsToken, ts.SyntaxKind.ExclamationEqualsEqualsToken, ts.SyntaxKind.EqualsEqualsToken,
                ts.SyntaxKind.ExclamationEqualsToken, ts.SyntaxKind.LessThanToken, ts.SyntaxKind.GreaterThanToken, ts.SyntaxKind.LessThanEqualsToken,
                ts.SyntaxKind.GreaterThanEqualsToken].includes(node.operatorToken.kind),
            literal: ts.isLiteralExpression(node) || ts.isTemplateExpression(node) || [ts.SyntaxKind.TrueKeyword, ts.SyntaxKind.FalseKeyword, ts.SyntaxKind.NullKeyword].includes(node.kind),
            import: ts.isImportDeclaration(node),
            catch: ts.isCatchClause(node),
        }

        return Object.keys(answers).filter((name) => answers[name])
    }

    facts(node, kind) {
        const facts = {}
        if (ts.isIdentifier(node) || ts.isPrivateIdentifier(node)) facts.name = node.text
        else if (node.name && (ts.isIdentifier(node.name) || ts.isStringLiteral(node.name))) facts.name = node.name.text
        Object.assign(facts, this.literal(node))
        if (ts.isBinaryExpression(node)) facts.operator = ts.tokenToString(node.operatorToken.kind)
        if (ts.isPrefixUnaryExpression(node) || ts.isPostfixUnaryExpression(node)) facts.operator = ts.tokenToString(node.operator)
        const modifiers = (ts.canHaveModifiers(node) ? ts.getModifiers(node) : undefined) ?? []
        if (modifiers.length) facts.modifiers = modifiers.map((modifier) => modifier.getText(this.sourceFile))
        const flags = this.flags(node, modifiers)
        if (flags.length) facts.flags = flags
        if ((ts.isParameter(node) || ts.isPropertySignature(node) || ts.isPropertyDeclaration(node) || ts.isVariableDeclaration(node)) && node.type) {
            facts.declared = this.type(this.checker.getTypeFromTypeNode(node.type), 'written')
        }
        if (ts.isFunctionLike(node) && node.type) facts.returns = this.type(this.checker.getTypeFromTypeNode(node.type), 'written')
        if (ts.isDeclaration(node) && node.name && !ts.isParameter(node) && !ts.isVariableDeclaration(node.parent?.parent ?? node) === false || MEMBERS.has(kind)) {
            const symbol = this.symbolOf(node)
            if (symbol) facts.symbol = symbol
        }
        if (ts.isVariableDeclaration(node) && ts.isIdentifier(node.name) && ts.isSourceFile(node.parent.parent.parent)) facts.symbol = `${shown}#${node.name.text}`
        if (ts.isExpression(node) && !(ts.isIdentifier(node) && ts.isDeclaration(node.parent) && node.parent.name === node) && !ts.isTypeNode(node)) {
            const type = this.checker.getTypeAtLocation(node)
            if (!(type.flags & ts.TypeFlags.Any)) facts.resolved = this.type(type, 'compiler')
        }
        if (ts.isCallExpression(node) || ts.isNewExpression(node)) Object.assign(facts, this.target(node))
        if (ts.isImportDeclaration(node) && node.importClause?.isTypeOnly) facts.extras = { typescript: { typeOnly: true } }
        if (ts.isImportDeclaration(node)) {
            const resolved = ts.resolveModuleName(node.moduleSpecifier.text, file, {}, ts.sys).resolvedModule
            if (resolved) facts.resolves = resolve(resolved.resolvedFileName)
        }

        return facts
    }

    literal(node) {
        if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) return { literal: 'string', value: node.text }
        if (ts.isNumericLiteral(node)) return { literal: /[.eE]/.test(node.text) ? 'float' : 'int', value: String(Number(node.text)) }
        if (ts.isTemplateExpression(node)) return { literal: 'interpolated' }
        if (node.kind === ts.SyntaxKind.TrueKeyword || node.kind === ts.SyntaxKind.FalseKeyword) return { literal: 'bool', value: node.kind === ts.SyntaxKind.TrueKeyword }
        if (node.kind === ts.SyntaxKind.NullKeyword) return { literal: 'null', value: null }

        return {}
    }

    flags(node, modifiers) {
        const flags = []
        if (ts.isParameter(node) && node.dotDotDotToken) flags.push('variadic')
        if ((ts.isParameter(node) || ts.isPropertySignature(node) || ts.isPropertyDeclaration(node)) && node.questionToken) flags.push('optional')
        if (ts.isSpreadElement(node) || ts.isSpreadAssignment(node)) flags.push('spread')
        if (modifiers.some((modifier) => modifier.kind === ts.SyntaxKind.AsyncKeyword)) flags.push('async')
        if (ts.isFunctionLike(node) && node.asteriskToken) flags.push('generator')

        return flags
    }

    symbolOf(node) {
        const names = []
        for (let at = node; at && !ts.isSourceFile(at); at = at.parent) {
            if (at.name && ts.isIdentifier(at.name) && (MEMBERS.has(KINDS[at.kind]))) names.unshift(at.name.text)
        }

        return names.length ? `${shown}#${names.join('.')}` : undefined
    }

    idOf(declaration) {
        const home = declaration.getSourceFile()
        const names = []
        for (let at = declaration; at && !ts.isSourceFile(at); at = at.parent) {
            if (at.name && ts.isIdentifier(at.name)) names.unshift(at.name.text)
        }
        if (ts.isConstructSignatureDeclaration(declaration)) names.push('new')
        const qualified = names.join('.')

        return home.fileName === this.sourceFile.fileName ? `${shown}#${qualified}` : qualified
    }

    target(node) {
        this.tree.calls++
        const declaration = this.checker.getResolvedSignature(node)?.declaration
        if (!declaration || ts.isJSDocSignature(declaration)) return {}
        this.tree.resolved++
        const container = declaration.parent && (ts.isInterfaceDeclaration(declaration.parent) || ts.isClassDeclaration(declaration.parent)) ? this.idOf(declaration.parent) : undefined
        const target = { symbol: this.idOf(declaration), name: declaration.name?.text ?? 'constructor' }
        if (container) target.type = container

        return { target }
    }

    type(type, origin) {
        const text = this.checker.typeToString(type, undefined, ts.TypeFormatFlags.NoTruncation | ts.TypeFormatFlags.UseFullyQualifiedType)
        const out = { text }
        if (type.isUnion()) {
            out.kind = 'union'
            out.members = type.types.map((member) => this.type(member, origin))
            if (type.types.some((member) => member.flags & (ts.TypeFlags.Null | ts.TypeFlags.Undefined))) out.nullable = true
        } else if (type.isIntersection()) {
            out.kind = 'intersection'
            out.members = type.types.map((member) => this.type(member, origin))
        } else if (type.isStringLiteral() || type.isNumberLiteral()) {
            out.kind = 'literal'
            out.value = String(type.value)
        } else if (type.flags & ts.TypeFlags.BooleanLiteral) {
            out.kind = 'literal'
            out.value = text === 'true'
        } else if (type.isTypeParameter()) {
            out.kind = 'parameter'
            out.name = text
        } else if (this.checker.isArrayType(type)) {
            out.kind = 'array'
            out.args = [this.type(this.checker.getTypeArguments(type)[0], origin)]
        } else if (type.getSymbol()?.declarations?.length && type.flags & ts.TypeFlags.Object) {
            const declaration = type.getSymbol().declarations[0]
            out.kind = declaration.name ? 'named' : 'object'
            if (declaration.name) out.name = this.idOf(declaration)
            const args = type.objectFlags & ts.ObjectFlags.Reference ? this.checker.getTypeArguments(type) : []
            if (args.length) out.args = args.map((argument) => this.type(argument, origin))
        } else if (type.flags & (ts.TypeFlags.String | ts.TypeFlags.Number | ts.TypeFlags.Boolean | ts.TypeFlags.Void | ts.TypeFlags.Undefined
            | ts.TypeFlags.Null | ts.TypeFlags.Unknown | ts.TypeFlags.Never | ts.TypeFlags.BigInt | ts.TypeFlags.ESSymbol)) {
            out.kind = 'keyword'
            out.name = text
            if (type.flags & (ts.TypeFlags.Null | ts.TypeFlags.Undefined)) out.nullable = true
        } else {
            out.kind = 'opaque'
        }
        out.origin = origin

        return out
    }
}

/** Every comment in `text` (which starts `base` characters into the file), found around every node of `sourceFile`. */
function scriptComments(sourceFile, base) {
    const found = new Map()
    const text = sourceFile.text
    const visit = (node) => {
        for (const range of [...(ts.getLeadingCommentRanges(text, node.pos) ?? []), ...(ts.getTrailingCommentRanges(text, node.end) ?? [])]) {
            const raw = text.slice(range.pos, range.end)
            found.set(range.pos, { start: base + range.pos, end: base + range.end, kind: raw.startsWith('/**') ? 'doc' : raw.startsWith('/*') ? 'block' : 'line' })
        }
        ts.forEachChild(node, visit)
    }
    visit(sourceFile)
    visit(sourceFile.endOfFileToken)

    return [...found.values()]
}

function comments(tree, found) {
    const sorted = found.sort((a, b) => a.start - b.start)
    const ends = new Map(sorted.map((comment) => [bytes[comment.start], bytes[comment.end]]))
    const raw = Buffer.from(source)

    return sorted.map((comment, id) => {
        const [start, end] = [bytes[comment.start], bytes[comment.end]]
        const out = { id, kind: comment.kind, text: source.slice(comment.start, comment.end), span: [start, end, lineAt(comment.start)] }
        const lineStart = raw.lastIndexOf(0x0a, start - 1) + 1
        if (raw.subarray(lineStart, start).toString().trim() !== '') {
            const owner = tree.spans.find((span) => span.end <= start && span.end > lineStart)
            if (owner) out.attached = owner.id
            out.trailing = true

            return out
        }
        let after = end
        for (;;) {
            while (after < raw.length && ' \t\r\n'.includes(String.fromCharCode(raw[after]))) after++
            if (!ends.has(after)) break
            after = ends.get(after)
        }
        const owner = tree.spans.find((span) => span.start === after)
        if (owner) out.attached = owner.id

        return out
    })
}

function program(fileName, text) {
    const host = ts.createCompilerHost({ strict: true, target: ts.ScriptTarget.ESNext, noEmit: true })
    const read = host.getSourceFile
    host.getSourceFile = (name, version, onError, create) => name === fileName
        ? ts.createSourceFile(name, text, version, true)
        : read.call(host, name, version, onError, create)
    const created = ts.createProgram([fileName], { strict: true, target: ts.ScriptTarget.ESNext, noEmit: true, lib: ['lib.esnext.d.ts', 'lib.dom.d.ts'] }, host)

    return { sourceFile: created.getSourceFile(fileName), checker: created.getTypeChecker() }
}

function typescriptFile() {
    const tree = new Tree()
    const { sourceFile, checker } = program(resolve(file), source)
    const root = new TypeScriptWriter(tree, sourceFile, checker, 0).node(sourceFile, undefined)
    root.span = [0, bytes[source.length], 1]
    tree.spans[0].start = 0

    return { tree, root, found: scriptComments(sourceFile, 0) }
}

/** A Vue file: its blocks from the SFC parser, its template from Vue's own AST, its script and expressions as TypeScript. */
function vueFile() {
    const tree = new Tree()
    const found = []
    const sfc = dom.parse(source, { parseMode: 'sfc', comments: true })
    const rootId = tree.number(0, source.length)
    const root = { id: rootId, kind: 'Component', role: 'markup', span: [0, bytes[source.length], 1] }
    const blocks = []
    for (const block of sfc.children) {
        if (block.type === dom.NodeTypes.COMMENT) {
            found.push({ start: block.loc.start.offset, end: block.loc.end.offset, kind: 'markup' })
            continue
        }
        if (block.type !== dom.NodeTypes.ELEMENT) continue
        blocks.push(vueBlock(tree, block, found))
    }
    if (blocks.length) root.children = blocks

    return { tree, root, found }
}

function vueBlock(tree, block, found) {
    const out = markup(tree, block, 'Block', 'blocks', { name: block.tag })
    const children = block.props.map((prop) => vueProp(tree, prop))
    if (block.tag === 'script') {
        const content = block.children[0]
        const { sourceFile, checker } = program(resolve(file) + '.ts', content ? content.content : '')
        if (content) {
            children.push(new TypeScriptWriter(tree, sourceFile, checker, content.loc.start.offset).node(sourceFile, 'children'))
            children.at(-1).span = [bytes[content.loc.start.offset], bytes[content.loc.end.offset], lineAt(content.loc.start.offset)]
            found.push(...scriptComments(sourceFile, content.loc.start.offset))
        }
    } else if (block.tag === 'template') {
        children.push(...block.children.flatMap((child) => vueTemplate(tree, child, found)))
    }
    if (children.length) out.children = children

    return out
}

function markup(tree, node, kind, field, facts = {}) {
    const [start, end] = [node.loc.start.offset, node.loc.end.offset]
    const out = { id: tree.number(start, end), kind, role: 'markup', span: [bytes[start], bytes[end], lineAt(start)], field }

    return Object.assign(out, facts)
}

function vueTemplate(tree, node, found) {
    switch (node.type) {
        case dom.NodeTypes.COMMENT:
            found.push({ start: node.loc.start.offset, end: node.loc.end.offset, kind: 'markup' })
            return []
        case dom.NodeTypes.TEXT:
            return node.content.trim() === '' ? [] : [markup(tree, node, 'Text', 'children')]
        case dom.NodeTypes.INTERPOLATION: {
            const out = markup(tree, node, 'Interpolation', 'children')
            out.children = [expression(tree, node.content, 'value')]
            return [out]
        }
        case dom.NodeTypes.ELEMENT: {
            const out = markup(tree, node, 'Element', 'children', { name: node.tag })
            const children = [...node.props.map((prop) => vueProp(tree, prop)), ...node.children.flatMap((child) => vueTemplate(tree, child, found))]
            if (children.length) out.children = children
            return [out]
        }
    }

    return []
}

function vueProp(tree, prop) {
    if (prop.type === dom.NodeTypes.ATTRIBUTE) {
        const facts = { name: prop.name }
        if (prop.value) facts.value = prop.value.content
        return markup(tree, prop, 'Attribute', 'attributes', facts)
    }
    const out = markup(tree, prop, 'Directive', 'attributes', { name: prop.rawName })
    if (/^[:@#]/.test(prop.rawName)) out.flags = ['shorthand']
    out.extras = { vue: { directive: { name: prop.name, modifiers: prop.modifiers.map((modifier) => modifier.content ?? modifier) } } }
    const children = []
    if (prop.arg) {
        children.push(prop.arg.isStatic
            ? markupArg(tree, prop.arg)
            : expression(tree, prop.arg, 'arg'))
    }
    if (prop.forParseResult) {
        const { source: iterable, value, key, index } = prop.forParseResult
        for (const alias of [value, key, index].filter(Boolean)) children.push(expression(tree, alias, 'alias'))
        children.push(expression(tree, iterable, 'iterable'))
    } else if (prop.exp) {
        children.push(expression(tree, prop.exp, 'value'))
    }
    if (children.length) out.children = children

    return out
}

function markupArg(tree, arg) {
    const [start, end] = [arg.loc.start.offset, arg.loc.end.offset]

    return { id: tree.number(start, end), kind: 'Identifier', role: 'other', span: [bytes[start], bytes[end], lineAt(start)], field: 'arg', name: arg.content }
}

/** A template expression, parsed as TypeScript, its spans pointing into the .vue file. */
function expression(tree, simple, field) {
    const start = simple.loc.start.offset
    const wrapped = ts.createSourceFile('expression.ts', `(${simple.loc.source})`, ts.ScriptTarget.ESNext, true)
    const parenthesized = wrapped.statements[0].expression
    const { checker } = program('expression.ts', `(${simple.loc.source})`)
    const writer = new TypeScriptWriter(tree, wrapped, checker, start - 1)
    const node = writer.node(parenthesized.expression, field)
    for (const typed of [node]) delete typed.resolved

    return stripResolved(node)
}

function stripResolved(node) {
    delete node.resolved
    delete node.target
    for (const child of node.children ?? []) stripResolved(child)

    return node
}

const { tree, root, found } = mode === 'vue' ? vueFile() : typescriptFile()
const emit = (line) => console.log(JSON.stringify(line))
const language = mode === 'vue' ? 'vue' : 'typescript'
emit({ header: { contract: 'tree', version: 1, language, bridge: { name: 'contract/samples/emit/frontend.mjs', version: '1' }, roots: [shown] } })
emit({ file: { path: shown, language, errors: 0, resolver: { tool: 'tsc', ran: true }, root, comments: comments(tree, found) } })
emit({ trailer: { files: 1, resolution: { calls: tree.calls, resolved: tree.resolved } } })
