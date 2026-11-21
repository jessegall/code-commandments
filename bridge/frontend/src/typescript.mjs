import ts from 'typescript'
import { Node, Tree } from './tree.mjs'
import { Source } from './source.mjs'

/** Declarations the contract gives role `member`, and a symbol id. */
const MEMBERS = new Set([
    ts.SyntaxKind.FunctionDeclaration, ts.SyntaxKind.ClassDeclaration, ts.SyntaxKind.InterfaceDeclaration,
    ts.SyntaxKind.TypeAliasDeclaration, ts.SyntaxKind.EnumDeclaration, ts.SyntaxKind.ModuleDeclaration,
    ts.SyntaxKind.MethodDeclaration, ts.SyntaxKind.PropertyDeclaration, ts.SyntaxKind.PropertySignature,
    ts.SyntaxKind.MethodSignature, ts.SyntaxKind.Constructor, ts.SyntaxKind.GetAccessor, ts.SyntaxKind.SetAccessor,
    ts.SyntaxKind.EnumMember,
])


const KEYWORD_TYPES = ts.TypeFlags.String | ts.TypeFlags.Number | ts.TypeFlags.Boolean | ts.TypeFlags.Void
    | ts.TypeFlags.Undefined | ts.TypeFlags.Null | ts.TypeFlags.Unknown | ts.TypeFlags.Never | ts.TypeFlags.BigInt
    | ts.TypeFlags.ESSymbol | ts.TypeFlags.NonPrimitive

/** How deep a type's structure is written before the rest is left opaque. */
const TYPE_DEPTH = 3



const COMPARISONS = new Map([
    [ts.SyntaxKind.EqualsEqualsEqualsToken, 'identical'], [ts.SyntaxKind.ExclamationEqualsEqualsToken, 'not-identical'],
    [ts.SyntaxKind.EqualsEqualsToken, 'equal'], [ts.SyntaxKind.ExclamationEqualsToken, 'not-equal'],
    [ts.SyntaxKind.LessThanToken, 'less'], [ts.SyntaxKind.LessThanEqualsToken, 'less-or-equal'],
    [ts.SyntaxKind.GreaterThanToken, 'greater'], [ts.SyntaxKind.GreaterThanEqualsToken, 'greater-or-equal'],
    [ts.SyntaxKind.InKeyword, 'in'],
])

const BINARIES = new Map([
    [ts.SyntaxKind.PlusToken, 'add'], [ts.SyntaxKind.MinusToken, 'subtract'], [ts.SyntaxKind.AsteriskToken, 'multiply'],
    [ts.SyntaxKind.SlashToken, 'divide'], [ts.SyntaxKind.PercentToken, 'modulo'], [ts.SyntaxKind.AsteriskAsteriskToken, 'power'],
    [ts.SyntaxKind.AmpersandToken, 'bit-and'], [ts.SyntaxKind.BarToken, 'bit-or'], [ts.SyntaxKind.CaretToken, 'bit-xor'],
    [ts.SyntaxKind.LessThanLessThanToken, 'shift-left'], [ts.SyntaxKind.GreaterThanGreaterThanToken, 'shift-right'],
    [ts.SyntaxKind.GreaterThanGreaterThanGreaterThanToken, 'shift-right'],
])

const COMPOUNDS = new Map([
    [ts.SyntaxKind.PlusEqualsToken, 'add'], [ts.SyntaxKind.MinusEqualsToken, 'subtract'], [ts.SyntaxKind.AsteriskEqualsToken, 'multiply'],
    [ts.SyntaxKind.SlashEqualsToken, 'divide'], [ts.SyntaxKind.PercentEqualsToken, 'modulo'], [ts.SyntaxKind.AsteriskAsteriskEqualsToken, 'power'],
    [ts.SyntaxKind.AmpersandEqualsToken, 'bit-and'], [ts.SyntaxKind.BarEqualsToken, 'bit-or'], [ts.SyntaxKind.CaretEqualsToken, 'bit-xor'],
    [ts.SyntaxKind.LessThanLessThanEqualsToken, 'shift-left'], [ts.SyntaxKind.GreaterThanGreaterThanEqualsToken, 'shift-right'],
    [ts.SyntaxKind.GreaterThanGreaterThanGreaterThanEqualsToken, 'shift-right'],
    [ts.SyntaxKind.BarBarEqualsToken, 'or'], [ts.SyntaxKind.AmpersandAmpersandEqualsToken, 'and'],
])

const UNARIES = new Map([
    [ts.SyntaxKind.ExclamationToken, 'not'], [ts.SyntaxKind.MinusToken, 'negate'], [ts.SyntaxKind.PlusToken, 'plus'],
    [ts.SyntaxKind.TildeToken, 'bit-not'],
])

const KEYWORD_NODES = new Set([
    ts.SyntaxKind.StringKeyword, ts.SyntaxKind.NumberKeyword, ts.SyntaxKind.BooleanKeyword, ts.SyntaxKind.VoidKeyword,
    ts.SyntaxKind.AnyKeyword, ts.SyntaxKind.UnknownKeyword, ts.SyntaxKind.NeverKeyword, ts.SyntaxKind.UndefinedKeyword,
    ts.SyntaxKind.ObjectKeyword, ts.SyntaxKind.BigIntKeyword, ts.SyntaxKind.SymbolKeyword, ts.SyntaxKind.NullKeyword,
])

/** Modifiers the node's own kind and facts say: `async` is the fact of that name, `default` the export's name. */
const SAID_ELSEWHERE = new Set([ts.SyntaxKind.AsyncKeyword])

/**
 * TypeScript translated into the version 5 generic tree (contract/CONTRACT.md). `offset` is where the parsed text
 * starts in the file, in UTF-16 units; the program's checker is there for script files and absent for template
 * expressions, which no checker types.
 */
export class Translator {
    constructor(tree, sourceFile, offset, program) {
        this.tree = tree
        this.sourceFile = sourceFile
        this.offset = offset
        this.program = program
        this.checker = program?.checker
    }

    new(kind, node) {
        return new Node(kind, this.offset + node.getStart(this.sourceFile), this.offset + node.end)
    }

    between(kind, start, end) {
        return new Node(kind, this.offset + start, this.offset + end)
    }

    binding(name) {
        if (ts.isIdentifier(name) || ts.isPrivateIdentifier(name)) return this.bound(name, this.new('binding', name).fact('name', name.text))

        return this.pattern(name)
    }

    /** Keeps the node written for a declared name, so a read of that name links to it. */
    bound(name, node) {
        this.tree.bindings ??= new Map()
        this.tree.bindings.set(name, node)

        return node
    }

    /** The reference, linked to the binding written in this file for the local name the compiler resolves it to. */
    linkedToBinding(name, reference) {
        const declaration = this.checker?.getSymbolAtLocation(name)?.declarations?.[0]
        const bound = (declaration?.name && this.tree.bindings?.get(declaration.name)) || (!this.checker && this.scope?.get(name.text))

        return bound ? reference.link('binding', bound) : reference
    }

    // The module ------------------------------------------------------------------------------------------------

    /** A module of `statements`, spanning `[start, end)` of the file. */
    module(statements, start, end) {
        const module = new Node('module', start, end)
        const imports = []
        const declarations = []
        const written = []
        for (const statement of statements) {
            if (ts.isImportDeclaration(statement)) imports.push(this.import(statement))
            else if (declares(statement)) declarations.push(this.statement(statement))
            else written.push(this.statement(statement))
        }

        return module.holdsAll('imports', imports).holdsAll('declarations', declarations).holdsAll('statements', written)
    }

    import(declaration) {
        const clause = declaration.importClause
        const source = ts.isStringLiteral(declaration.moduleSpecifier) ? declaration.moduleSpecifier.text : declaration.moduleSpecifier.getText(this.sourceFile)
        const named = clause?.namedBindings
        const form = !clause ? 'side-effect' : named && ts.isNamespaceImport(named) ? 'module' : 'names'
        const node = this.new('import', declaration).fact('source', source).fact('form', form).fact('typeOnly', clause?.isTypeOnly)
        const resolves = this.checker && ts.isStringLiteral(declaration.moduleSpecifier) ? this.program.resolve(source, this.sourceFile.fileName) : undefined
        node.fact('resolves', resolves)
        const bindings = []
        if (clause?.name) bindings.push(this.new('import-binding', clause.name).fact('name', 'default').fact('refers', this.refersOf(clause.name)).holds('alias', this.binding(clause.name)))
        if (named && ts.isNamespaceImport(named)) node.holds('alias', this.binding(named.name))
        if (named && ts.isNamedImports(named)) {
            for (const element of named.elements) {
                const binding = this.new('import-binding', element).fact('name', (element.propertyName ?? element.name).text).fact('refers', this.refersOf(element.name))
                if (element.propertyName) binding.holds('alias', this.binding(element.name))
                else this.bound(element.name, binding)
                bindings.push(binding)
            }
        }

        return node.holdsAll('bindings', bindings)
    }

    // Declarations ----------------------------------------------------------------------------------------------

    modifiers(node) {
        return ((ts.canHaveModifiers(node) ? ts.getModifiers(node) : undefined) ?? [])
            .filter((modifier) => !SAID_ELSEWHERE.has(modifier.kind))
            .map((modifier) => ts.tokenToString(modifier.kind))
    }

    async(node) {
        return ((ts.canHaveModifiers(node) ? ts.getModifiers(node) : undefined) ?? []).some((modifier) => modifier.kind === ts.SyntaxKind.AsyncKeyword)
    }

    attributes(node) {
        return (ts.canHaveDecorators(node) ? ts.getDecorators(node) ?? [] : []).map((decorator) => {
            const callee = ts.isCallExpression(decorator.expression) ? decorator.expression.expression : decorator.expression

            return this.new('annotation', decorator)
                .fact('name', callee.getText(this.sourceFile))
                .fact('refers', this.checker ? this.refersOf(ts.isPropertyAccessExpression(callee) ? callee : callee) : undefined)
                .holds('callee', this.expression(callee))
                .holdsAll('arguments', ts.isCallExpression(decorator.expression) ? decorator.expression.arguments.map((argument) => this.argument(argument)) : [])
        })
    }

    function(node, form) {
        const name = node.kind === ts.SyntaxKind.Constructor ? 'constructor' : node.name && !ts.isComputedPropertyName(node.name) ? node.name.text : undefined
        const out = this.new('function', node)
            .fact('name', name === '' ? undefined : name)
            .fact('symbol', this.checker ? this.symbolOf(node) : undefined)
            .fact('form', form)
            .fact('modifiers', this.modifiers(node))
            .fact('async', this.async(node))
            .fact('generator', node.asteriskToken !== undefined)
            .fact('incomplete', this.incomplete(node))
            .holdsAll('attributes', this.attributes(node))
            .holdsAll('typeParameters', (node.typeParameters ?? []).map((parameter) => this.new('type-parameter', parameter).fact('name', parameter.name.text)
                .holds('constraint', parameter.constraint && this.type(parameter.constraint)).holds('default', parameter.default && this.type(parameter.default))))
            .holdsAll('parameters', node.parameters.map((parameter) => this.parameter(parameter)))
            .holds('returnType', node.type && this.type(node.type))
        if (node.body) out.holds('body', ts.isBlock(node.body) ? this.block(node.body) : this.expression(node.body))

        return out
    }

    parameter(parameter) {
        return this.new('parameter', parameter)
            .fact('collects', parameter.dotDotDotToken ? 'rest' : undefined)
            .fact('optional', parameter.questionToken !== undefined)
            .fact('modifiers', this.modifiers(parameter))
            .holdsAll('attributes', this.attributes(parameter))
            .holds('binding', this.binding(parameter.name))
            .holds('declaredType', parameter.type && this.type(parameter.type))
            .holds('default', parameter.initializer && this.expression(parameter.initializer))
    }

    type(node) {
        if (ts.isParenthesizedTypeNode(node)) return this.type(node.type)
        if (KEYWORD_NODES.has(node.kind)) return this.new('type-expression', node).fact('form', node.kind === ts.SyntaxKind.NullKeyword ? 'literal' : 'keyword').fact('name', node.getText(this.sourceFile))
        if (ts.isLiteralTypeNode(node)) return this.new('type-expression', node).fact('form', 'literal')
        if (ts.isUnionTypeNode(node)) return this.new('type-expression', node).fact('form', 'union').holdsAll('members', node.types.map((member) => this.type(member)))
        if (ts.isIntersectionTypeNode(node)) return this.new('type-expression', node).fact('form', 'intersection').holdsAll('members', node.types.map((member) => this.type(member)))
        if (ts.isArrayTypeNode(node)) return this.new('type-expression', node).fact('form', 'array').holds('element', this.type(node.elementType))
        if (ts.isTypeReferenceNode(node) || ts.isExpressionWithTypeArguments(node)) {
            const name = ts.isTypeReferenceNode(node) ? node.typeName : node.expression

            return this.resolvedAs(this.new('type-expression', node)
                .fact('form', 'named')
                .fact('name', name.getText(this.sourceFile))
                .fact('refers', this.checker ? this.refersOf(ts.isQualifiedName(name) ? name.right : name) : undefined)
                .holdsAll('typeArguments', (node.typeArguments ?? []).map((argument) => this.type(argument))), node)
        }
        if (ts.isTypeLiteralNode(node)) return this.resolvedAs(this.new('type-expression', node).fact('form', 'object'), node)
        if (ts.isFunctionTypeNode(node) || ts.isConstructorTypeNode(node)) {
            return this.new('type-expression', node).fact('form', 'function')
                .holdsAll('parameters', node.parameters.map((parameter) => this.parameter(parameter)))
                .holds('returnType', node.type && this.type(node.type))
        }
        if (ts.isTupleTypeNode(node)) {
            return this.new('tuple-type', node).holdsAll('elements', node.elements.map((element) => ts.isNamedTupleMember(element)
                ? this.new('tuple-element', element).fact('label', element.name.text).holds('type', this.type(element.type))
                : this.new('tuple-element', element).holds('type', this.type(element))))
        }
        if (ts.isTypeQueryNode(node) || ts.isTypeReferenceNode(node)) return this.new('type-expression', node).fact('form', 'named')

        return this.new('type-expression', node).fact('form', 'opaque')
    }

    /**
     * Every doc link the file's JSDoc makes (`{@link X}`, `{@linkcode X}`, `{@linkplain X}`, `@see X`), at its offset,
     * as the contract's ref: the symbol the compiler resolves it to, or none; a name whose head the file imports is
     * someone else's to keep, so only an unresolved name of the file's own is owned here.
     */
    docLinks() {
        const imported = new Set(this.sourceFile.statements.filter(ts.isImportDeclaration)
            .flatMap((declaration) => {
                const clause = declaration.importClause
                if (!clause) return []
                const names = clause.name ? [clause.name.text] : []
                const bindings = clause.namedBindings
                if (bindings && ts.isNamespaceImport(bindings)) names.push(bindings.name.text)
                if (bindings && ts.isNamedImports(bindings)) names.push(...bindings.elements.map((element) => element.name.text))

                return names
            }))
        const links = []
        const linked = (name) => {
            if (!name) return
            const text = name.getText(this.sourceFile)
            let symbol = this.checker?.getSymbolAtLocation(ts.isQualifiedName(name) || ts.isPropertyAccessExpression(name) ? name.right ?? name.name : name)
            if (symbol && symbol.flags & ts.SymbolFlags.Alias) symbol = this.checker.getAliasedSymbol(symbol)
            const declaration = symbol?.declarations?.find(declaresSymbol)
            const resolved = declaration ? this.idOf(declaration) : undefined
            const ref = { text }
            if (resolved) ref.symbol = resolved
            else ref.ownedHere = !imported.has(text.split(/[.#]/)[0])
            links.push({ at: this.offset + name.getStart(this.sourceFile), ref })
        }
        const visit = (node) => {
            if (ts.isJSDocLink(node) || ts.isJSDocLinkCode(node) || ts.isJSDocLinkPlain(node)) linked(node.name)
            if (ts.isJSDocSeeTag(node)) linked(node.name?.name)
            ts.forEachChild(node, visit)
            for (const doc of node.jsDoc ?? []) visit(doc)
        }
        visit(this.sourceFile)

        return links
    }

    /** The written type node's type as the compiler reads it, its fields and members included, as its resolvedType. */
    resolvedAs(out, node) {
        if (!this.checker || !this.tree.checked) return out
        const type = this.checker.getTypeFromTypeNode(node)
        if (type.flags & ts.TypeFlags.Any || isErrorType(type)) return out
        // A literal { … } is its own shape even where an alias names it, so it is described by its fields.
        if (ts.isTypeLiteralNode(node)) {
            return out.fact('resolvedType', this.tree.typeIndex(node, () => ({ text: this.program.printed(type), ...this.shapeOf(type, 'compiler', 1), nullable: nullable(type), origin: 'compiler' })))
        }

        return out.fact('resolvedType', this.tree.typeIndex(type, () => this.describedType(type, 'compiler')))
    }

    type_declaration(node, form) {
        const heritage = (token) => (node.heritageClauses ?? []).filter((clause) => clause.token === token).flatMap((clause) => clause.types.map((type) => this.type(type)))
        const members = []
        const implicit = []
        for (const member of node.members ?? []) {
            const written = this.member(member)
            if (!written) continue
            members.push(written)
            if (member.kind === ts.SyntaxKind.Constructor) {
                for (const [index, parameter] of member.parameters.entries()) {
                    if (ts.isParameterPropertyDeclaration(parameter, member)) implicit.push(written.parts.parameters[index])
                }
            }
        }
        const out = this.new('type', node)
            .fact('name', node.name?.text)
            .fact('symbol', this.checker ? this.symbolOf(node) : undefined)
            .fact('form', form)
            .fact('modifiers', this.modifiers(node))
            .holdsAll('attributes', this.attributes(node))
            .holdsAll('typeParameters', (node.typeParameters ?? []).map((parameter) => this.new('type-parameter', parameter).fact('name', parameter.name.text)))
            .holdsAll('bases', heritage(ts.SyntaxKind.ExtendsKeyword))
            .holdsAll('interfaces', heritage(ts.SyntaxKind.ImplementsKeyword))
            .holdsAll('writtenMembers', members)
        if (implicit.length) out.link('implicitMembers', implicit)

        return out
    }

    member(member) {
        if (ts.isMethodDeclaration(member) || ts.isMethodSignature(member)) return this.function(member, 'method')
        if (member.kind === ts.SyntaxKind.Constructor) return this.function(member, 'constructor')
        if (ts.isGetAccessorDeclaration(member)) return this.function(member, 'get')
        if (ts.isSetAccessorDeclaration(member)) return this.function(member, 'set')
        if (ts.isPropertyDeclaration(member) || ts.isPropertySignature(member)) {
            const end = member.initializer?.end ?? member.type?.end ?? member.name.end

            return this.new('field', member)
                .fact('modifiers', this.modifiers(member))
                .holdsAll('attributes', this.attributes(member))
                .holds('declaredType', member.type && this.type(member.type))
                .holdsAll('declarators', [this.between('declarator', member.name.getStart(this.sourceFile), end)
                    .holds('binding', this.bound(member.name, this.new('binding', member.name).fact('name', ts.isComputedPropertyName(member.name) ? undefined : member.name.text).fact('symbol', this.checker ? this.symbolOf(member) : undefined)))
                    .holds('initializer', member.initializer && this.expression(member.initializer))])
        }
        if (ts.isEnumMember(member)) {
            return this.new('enum-case', member).fact('name', ts.isComputedPropertyName(member.name) ? undefined : member.name.text)
                .fact('symbol', this.checker ? this.symbolOf(member) : undefined).holds('value', member.initializer && this.expression(member.initializer))
        }
        if (ts.isSemicolonClassElement(member)) return undefined

        return this.unsupported(member, 'member')
    }

    // Statements ------------------------------------------------------------------------------------------------

    block(block) {
        return this.new('block', block).holdsAll('statements', block.statements.map((statement) => this.statement(statement)))
    }

    statement(node) {
        if (ts.isBlock(node)) return this.block(node)
        if (ts.isVariableStatement(node)) return this.variable(node, node.declarationList).fact('modifiers', this.modifiers(node))
        if (ts.isExpressionStatement(node)) return this.new('expression-statement', node).holds('expression', this.expression(node.expression))
        if (ts.isIfStatement(node)) {
            return this.new('if', node).holds('condition', this.expression(node.expression)).holds('then', this.statement(node.thenStatement))
                .holds('else', node.elseStatement && this.statement(node.elseStatement))
        }
        if (ts.isForStatement(node)) {
            const initializer = node.initializer && (ts.isVariableDeclarationList(node.initializer) ? this.variable(node.initializer, node.initializer) : this.expression(node.initializer))

            return this.new('loop', node).fact('form', 'for').holdsAll('initializer', [initializer])
                .holds('condition', node.condition && this.expression(node.condition))
                .holdsAll('steps', [node.incrementor && this.expression(node.incrementor)])
                .holds('body', this.statement(node.statement))
        }
        if (ts.isForOfStatement(node) || ts.isForInStatement(node)) {
            const bindings = ts.isVariableDeclarationList(node.initializer) ? node.initializer.declarations.map((declaration) => this.binding(declaration.name)) : [this.expression(node.initializer)]

            return this.new('loop', node).fact('form', 'for-each').fact('async', ts.isForOfStatement(node) && node.awaitModifier !== undefined)
                .holdsAll('bindings', bindings).holds('iterable', this.expression(node.expression)).holds('body', this.statement(node.statement))
        }
        if (ts.isWhileStatement(node)) return this.new('loop', node).fact('form', 'while').holds('condition', this.expression(node.expression)).holds('body', this.statement(node.statement))
        if (ts.isDoStatement(node)) return this.new('loop', node).fact('form', 'do-while').holds('body', this.statement(node.statement)).holds('condition', this.expression(node.expression))
        if (ts.isReturnStatement(node)) return this.new('return', node).holdsAll('values', [node.expression && this.expression(node.expression)])
        if (ts.isThrowStatement(node)) return this.new('throw', node).holds('value', this.expression(node.expression))
        if (ts.isBreakStatement(node) || ts.isContinueStatement(node)) {
            return this.new(ts.isBreakStatement(node) ? 'break' : 'continue', node).holds('label', node.label && this.new('reference', node.label).fact('name', node.label.text).fact('form', 'name'))
        }
        if (ts.isTryStatement(node)) {
            const clause = node.catchClause
            const handler = clause && this.new('handler', clause).fact('catchForm', 'catch-all')
                .holds('binding', clause.variableDeclaration && this.binding(clause.variableDeclaration.name)).holds('body', this.block(clause.block))

            return this.new('try', node).holds('body', this.block(node.tryBlock)).holdsAll('handlers', [handler]).holds('finally', node.finallyBlock && this.block(node.finallyBlock))
        }
        if (ts.isSwitchStatement(node)) return this.switch(node)
        if (ts.isLabeledStatement(node)) return this.new('label', node).holds('binding', this.binding(node.label)).holds('statement', this.statement(node.statement))
        if (ts.isEmptyStatement(node)) return this.new('no-op', node)
        if (ts.isFunctionDeclaration(node)) return this.function(node, 'function')
        if (ts.isClassDeclaration(node)) return this.type_declaration(node, 'class')
        if (ts.isInterfaceDeclaration(node)) return this.type_declaration(node, 'interface')
        if (ts.isEnumDeclaration(node)) return this.type_declaration(node, 'enum')
        if (ts.isTypeAliasDeclaration(node)) {
            return this.new('alias', node).fact('name', node.name.text).fact('symbol', this.checker ? this.symbolOf(node) : undefined)
                .holdsAll('typeParameters', (node.typeParameters ?? []).map((parameter) => this.new('type-parameter', parameter).fact('name', parameter.name.text)))
                .holds('underlyingType', this.type(node.type))
        }
        if (ts.isModuleDeclaration(node) && node.body && ts.isModuleBlock(node.body)) {
            return this.new('type', node).fact('name', ts.isIdentifier(node.name) ? node.name.text : node.name.text).fact('form', 'object')
                .fact('modifiers', this.modifiers(node)).holdsAll('writtenMembers', node.body.statements.map((statement) => this.statement(statement)))
        }
        if (ts.isImportDeclaration(node)) return this.import(node)

        return this.unsupported(node, kindName(node))
    }

    variable(node, list) {
        const form = list.flags & ts.NodeFlags.Const ? 'const' : list.flags & ts.NodeFlags.Let ? 'let' : list.flags & ts.NodeFlags.Using ? 'using' : 'var'
        const typed = list.declarations.find((declaration) => declaration.type)

        return this.new('variable', node).fact('form', form)
            .holds('declaredType', typed && this.type(typed.type))
            .holdsAll('declarators', list.declarations.map((declaration) => this.new('declarator', declaration)
                .holds('binding', this.binding(declaration.name).fact('symbol', this.checker ? this.symbolOf(declaration) : undefined))
                .holds('initializer', declaration.initializer && this.expression(declaration.initializer))))
    }

    switch(node) {
        const clauses = node.caseBlock.clauses
        const jumps = (statement) => statement && (ts.isBreakStatement(statement) || ts.isReturnStatement(statement) || ts.isThrowStatement(statement) || ts.isContinueStatement(statement))
        const arms = clauses.map((clause, index) => {
            const statements = clause.statements.map((statement) => this.statement(statement))
            const body = statements.length === 1 && statements[0].kind === 'block' ? statements[0]
                : statements.length ? new Node('block', statements[0].start, statements.at(-1).end).holdsAll('statements', statements)
                    : new Node('block', this.offset + clause.end, this.offset + clause.end)
            const patterns = ts.isCaseClause(clause) ? [this.new('pattern', clause.expression).fact('form', 'value').holds('value', this.expression(clause.expression))] : []

            return this.new('arm', clause).fact('default', ts.isDefaultClause(clause)).holdsAll('patterns', patterns).holds('body', body)
                .fact('fallsThrough', index < clauses.length - 1 && !jumps(clause.statements.at(-1)))
        })

        return this.new('dispatch', node).fact('form', 'switch').holds('subject', this.expression(node.expression)).holdsAll('arms', arms)
    }

    unsupported(node, construct) {
        const parts = []
        ts.forEachChild(node, (child) => {
            if (ts.isExpression(child) && !ts.isIdentifier(child)) parts.push(this.expression(child))
        })

        return this.new('unsupported', node).fact('construct', construct).holdsAll('parts', parts)
    }

    // Expressions -----------------------------------------------------------------------------------------------

    expression(node) {
        const out = this.translated(node)
        if (this.checker && this.tree.checked && typed(node) && out.kind !== 'unsupported') {
            this.tree.expressions++
            const type = this.checker.getTypeAtLocation(node)
            if (!(type.flags & ts.TypeFlags.Any) && !isErrorType(type)) {
                this.tree.typed++
                out.fact('resolvedType', this.tree.typeIndex(type, () => this.describedType(type, 'compiler')))
            }
        }

        return out
    }

    translated(node) {
        if (ts.isParenthesizedExpression(node)) return this.expression(node.expression)
        if (ts.isIdentifier(node)) {
            if (node.text === 'undefined') return this.new('literal', node).fact('literalKind', 'undefined')

            return this.linkedToBinding(node, this.new('reference', node).fact('name', node.text).fact('form', 'name').fact('refers', this.checker ? this.refersOf(node) : undefined))
        }
        if (ts.isPrivateIdentifier(node)) return this.new('reference', node).fact('name', node.text).fact('form', 'name')
        if (node.kind === ts.SyntaxKind.ThisKeyword) return this.new('reference', node).fact('name', 'this').fact('form', 'self')
        if (node.kind === ts.SyntaxKind.SuperKeyword) return this.new('reference', node).fact('name', 'super').fact('form', 'parent')
        if (ts.isPropertyAccessExpression(node)) {
            return this.new('access', node).fact('form', this.isStatic(node.expression) ? 'static' : 'instance').fact('nullConditional', node.questionDotToken !== undefined)
                .holds('subject', this.expression(node.expression))
                .holds('member', this.new('reference', node.name).fact('name', node.name.text).fact('form', 'name').fact('refers', this.checker ? this.refersOf(node) : undefined))
        }
        if (ts.isElementAccessExpression(node)) {
            return this.new('index', node).fact('nullConditional', node.questionDotToken !== undefined)
                .holds('subject', this.expression(node.expression)).holds('key', this.expression(node.argumentExpression))
        }
        if (ts.isCallExpression(node)) {
            return this.new('call', node).fact('target', this.checker ? this.target(node) : undefined)
                .holds('callee', this.expression(node.expression)).holdsAll('typeArguments', (node.typeArguments ?? []).map((argument) => this.type(argument)))
                .holdsAll('arguments', node.arguments.map((argument) => this.argument(argument)))
        }
        if (ts.isNewExpression(node)) {
            const named = ts.isIdentifier(node.expression) || ts.isPropertyAccessExpression(node.expression)
            const type = named && this.new('type-expression', node.expression).fact('form', 'named').fact('refers', this.checker ? this.refersOf(node.expression) : undefined)
                .holdsAll('typeArguments', (node.typeArguments ?? []).map((argument) => this.type(argument)))

            return this.new('construction', node).fact('target', this.checker ? this.target(node) : undefined)
                .holds('type', type || undefined).holds('callee', named ? undefined : this.expression(node.expression))
                .holdsAll('arguments', (node.arguments ?? []).map((argument) => this.argument(argument)))
        }
        if (ts.isBinaryExpression(node)) return this.binary(node)
        if (ts.isPrefixUnaryExpression(node) || ts.isPostfixUnaryExpression(node)) {
            if (node.operator === ts.SyntaxKind.PlusPlusToken || node.operator === ts.SyntaxKind.MinusMinusToken) {
                return this.new('assignment', node).fact('mode', node.operator === ts.SyntaxKind.PlusPlusToken ? 'increment' : 'decrement')
                    .fact('prefix', ts.isPrefixUnaryExpression(node)).holdsAll('targets', [this.expression(node.operand)])
            }

            return this.new('unary', node).fact('operator', UNARIES.get(node.operator)).holds('operand', this.expression(node.operand))
        }
        if (ts.isNonNullExpression(node)) return this.new('unary', node).fact('operator', 'assert-present').holds('operand', this.expression(node.expression))
        if (ts.isTypeOfExpression(node)) return this.new('unary', node).fact('operator', 'typeof').holds('operand', this.expression(node.expression))
        if (ts.isDeleteExpression(node)) return this.new('unary', node).fact('operator', 'delete').holds('operand', this.expression(node.expression))
        if (ts.isVoidExpression(node)) return this.new('unary', node).fact('operator', 'void').holds('operand', this.expression(node.expression))
        if (ts.isConditionalExpression(node)) {
            return this.new('conditional', node).holds('condition', this.expression(node.condition))
                .holds('consequent', this.expression(node.whenTrue)).holds('alternative', this.expression(node.whenFalse))
        }
        if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) return this.new('literal', node).fact('literalKind', 'string').said('value', node.text)
        if (ts.isNumericLiteral(node)) {
            return this.new('literal', node).fact('literalKind', /[.eE]/.test(node.text) && !/^0[xob]/i.test(node.text) ? 'float' : 'integer').said('value', String(Number(node.text)))
        }
        if (ts.isBigIntLiteral(node)) return this.new('literal', node).fact('literalKind', 'integer').said('value', node.text.slice(0, -1))
        if (node.kind === ts.SyntaxKind.TrueKeyword || node.kind === ts.SyntaxKind.FalseKeyword) {
            return this.new('literal', node).fact('literalKind', 'boolean').said('value', node.kind === ts.SyntaxKind.TrueKeyword)
        }
        if (node.kind === ts.SyntaxKind.NullKeyword) return this.new('literal', node).fact('literalKind', 'null')
        if (ts.isTemplateExpression(node)) {
            const parts = []
            if (node.head.text) parts.push(this.new('text', node.head).said('value', node.head.text))
            for (const span of node.templateSpans) {
                parts.push(this.expression(span.expression))
                if (span.literal.text) parts.push(this.new('text', span.literal).said('value', span.literal.text))
            }

            return this.new('interpolation', node).holdsAll('parts', parts)
        }
        if (ts.isArrowFunction(node)) return this.function(node, 'arrow')
        if (ts.isFunctionExpression(node)) return this.function(node, 'closure')
        if (ts.isClassExpression(node)) return this.type_declaration(node, 'class')
        if (ts.isObjectLiteralExpression(node)) return this.new('collection', node).fact('form', 'object').holdsAll('entries', node.properties.map((property) => this.entry(property)))
        if (ts.isArrayLiteralExpression(node)) {
            return this.new('collection', node).fact('form', 'list').holdsAll('entries', node.elements.map((element) => ts.isSpreadElement(element)
                ? this.new('entry', element).fact('expansion', 'positional').holds('value', this.expression(element.expression))
                : ts.isOmittedExpression(element) ? undefined : this.new('entry', element).holds('value', this.expression(element))))
        }
        if (ts.isAwaitExpression(node)) return this.new('await', node).holds('operand', this.expression(node.expression))
        if (ts.isYieldExpression(node)) return this.new('yield', node).fact('delegates', node.asteriskToken !== undefined).holds('value', node.expression && this.expression(node.expression))
        if (ts.isAsExpression(node) || ts.isTypeAssertionExpression(node) || ts.isSatisfiesExpression(node)) {
            return this.new('cast', node).fact('form', 'assertion').holds('operand', this.expression(node.expression)).holds('targetType', this.type(node.type))
        }
        if (ts.isSpreadElement(node)) return this.expression(node.expression)

        return this.unsupported(node, kindName(node))
    }

    isStatic(subject) {
        // The checker resolves `this` to its class, but `this.x` and `super.x` read the instance.
        if (!this.checker || subject.kind === ts.SyntaxKind.ThisKeyword || subject.kind === ts.SyntaxKind.SuperKeyword) return false
        const symbol = this.checker.getSymbolAtLocation(subject)
        const target = symbol && symbol.flags & ts.SymbolFlags.Alias ? this.checker.getAliasedSymbol(symbol) : symbol

        return target !== undefined && (target.flags & (ts.SymbolFlags.Class | ts.SymbolFlags.Enum | ts.SymbolFlags.Module)) !== 0
    }

    argument(argument) {
        if (ts.isSpreadElement(argument)) return this.new('argument', argument).fact('spread', 'positional').holds('value', this.expression(argument.expression))

        return this.new('argument', argument).holds('value', this.expression(argument))
    }

    entry(property) {
        if (ts.isSpreadAssignment(property)) return this.new('entry', property).fact('expansion', 'keyed').holds('value', this.expression(property.expression))
        if (ts.isShorthandPropertyAssignment(property)) {
            return this.new('entry', property).holds('key', this.new('reference', property.name).fact('name', property.name.text).fact('form', 'name'))
                .holds('value', this.new('reference', property.name).fact('name', property.name.text).fact('form', 'name').fact('refers', this.checker ? this.refersOf(property.name) : undefined))
        }
        const key = ts.isComputedPropertyName(property.name) ? this.expression(property.name.expression)
            : ts.isIdentifier(property.name) || ts.isPrivateIdentifier(property.name) ? this.new('reference', property.name).fact('name', property.name.text).fact('form', 'name')
                : this.expression(property.name)
        const value = ts.isPropertyAssignment(property) ? this.expression(property.initializer)
            : ts.isMethodDeclaration(property) ? this.function(property, 'method')
                : ts.isGetAccessorDeclaration(property) ? this.function(property, 'get') : this.function(property, 'set')

        return this.new('entry', property).holds('key', key).holds('value', value)
    }

    binary(node) {
        const operator = node.operatorToken.kind
        if (operator === ts.SyntaxKind.EqualsToken) return this.new('assignment', node).fact('mode', 'assign').holdsAll('targets', [this.expression(node.left)]).holdsAll('values', [this.expression(node.right)])
        if (operator === ts.SyntaxKind.QuestionQuestionEqualsToken) return this.new('assignment', node).fact('mode', 'fallback').holdsAll('targets', [this.expression(node.left)]).holdsAll('values', [this.expression(node.right)])
        if (COMPOUNDS.has(operator)) {
            return this.new('assignment', node).fact('mode', 'compound').fact('operator', COMPOUNDS.get(operator))
                .holdsAll('targets', [this.expression(node.left)]).holdsAll('values', [this.expression(node.right)])
        }
        if (operator === ts.SyntaxKind.QuestionQuestionToken) return this.new('fallback', node).fact('trigger', 'null').holds('subject', this.expression(node.left)).holds('replacement', this.expression(node.right))
        if (operator === ts.SyntaxKind.AmpersandAmpersandToken || operator === ts.SyntaxKind.BarBarToken) {
            return this.new('logical', node).fact('operator', operator === ts.SyntaxKind.AmpersandAmpersandToken ? 'and' : 'or')
                .holds('left', this.expression(node.left)).holds('right', this.expression(node.right))
        }
        if (COMPARISONS.has(operator)) {
            const [left, right] = [this.expression(node.left), this.expression(node.right)]
            const comparison = COMPARISONS.get(operator)

            return this.new('comparison', node).fact('operators', [comparison]).fact('operator', comparison).holdsAll('operands', [left, right]).link('left', left).link('right', right)
        }
        if (operator === ts.SyntaxKind.InstanceOfKeyword) {
            return this.new('type-test', node).fact('form', 'instanceof').holds('subject', this.expression(node.left))
                .holds('testedType', this.new('type-expression', node.right).fact('form', 'named').fact('refers', this.checker ? this.refersOf(node.right) : undefined))
        }
        if (BINARIES.has(operator)) return this.new('binary', node).fact('operator', BINARIES.get(operator)).holds('left', this.expression(node.left)).holds('right', this.expression(node.right))

        return this.unsupported(node, 'comma')
    }

    // Patterns --------------------------------------------------------------------------------------------------

    pattern(name) {
        if (ts.isObjectBindingPattern(name) || ts.isArrayBindingPattern(name)) {
            const elements = name.elements.filter((element) => !ts.isOmittedExpression(element))

            return this.new('pattern', name).fact('form', ts.isObjectBindingPattern(name) ? 'mapping' : 'sequence')
                .holdsAll('subpatterns', elements.map((element) => {
                    const inner = this.binding(element.name)
                    if (element.dotDotDotToken) return this.new('pattern', element).fact('form', 'rest').holdsAll('bindings', [inner])

                    return element.initializer || element.propertyName
                        ? this.new('pattern', element).fact('form', 'binding').holdsAll('bindings', [inner]).holds('value', element.initializer && this.expression(element.initializer))
                        : inner
                }))
        }

        return this.new('binding', name).fact('name', name.text)
    }

    /** Whether the parser recovered the declaration from an error: it flags the node, or an error sits in its header. */
    incomplete(node) {
        if ((node.flags & ts.NodeFlags.ThisNodeHasError) !== 0) return true
        const start = node.getStart(this.sourceFile)
        const end = node.body ? node.body.getStart(this.sourceFile) : node.end

        return (this.sourceFile.parseDiagnostics ?? []).some((diagnostic) => diagnostic.start >= start && diagnostic.start <= end)
    }

    describedType(type, origin, depth) {
        return this.typeOf(type, origin, depth)
    }

    /** An object type as its fields, each named and typed, optional where it may be left out. */
    shapeOf(type, origin, depth) {
        const fields = type.getProperties().map((property) => {
            const field = { name: this.fieldName(property), type: this.typeOf(this.checker.getTypeOfSymbol(property), origin, depth) }
            if (property.flags & ts.SymbolFlags.Optional) field.optional = true

            return field
        })

        return { kind: 'object', fields }
    }

    /** The symbol id this node declares, when it is a declaration the contract names. */
    symbolOf(node) {
        if (!declaresSymbol(node)) return undefined

        return this.idOf(node)
    }

    /** The symbol id of `declaration`: `<path>#Qualified.name`, or the bare global name for the standard library. */
    idOf(declaration) {
        const names = []
        for (let at = declaration; at && !ts.isSourceFile(at); at = at.parent) {
            if (!namesItsScope(at) || ts.isConstructSignatureDeclaration(at)) continue
            const name = declaredName(at)
            if (name === undefined) return undefined
            names.unshift(name)
        }
        if (ts.isConstructSignatureDeclaration(declaration)) names.push('new')
        if (!names.length) return undefined
        const qualified = names.join('.')
        const home = declaration.getSourceFile()
        if (this.program.isLibrary(home)) return qualified

        return `${this.program.shown(home.fileName)}#${qualified}`
    }

    /** The symbol id a name or an import binding names, when it resolves to a declaration the contract names. */
    refersOf(node) {
        const property = ts.isPropertyAccessExpression(node)
        if (!property && (!ts.isIdentifier(node) || !(isNameRead(node) || isImportBinding(node)))) return undefined
        const shorthand = node.parent && ts.isShorthandPropertyAssignment(node.parent) && node.parent.name === node
        let symbol = shorthand
            ? this.checker.getShorthandAssignmentValueSymbol(node.parent)
            : this.checker.getSymbolAtLocation(property ? node.name : node)
        if (!symbol) return undefined
        if (symbol.flags & ts.SymbolFlags.Alias) symbol = this.checker.getAliasedSymbol(symbol)
        const declaration = symbol.declarations?.find(declaresSymbol)
        if (!declaration) return undefined
        this.tree.names++

        return this.idOf(declaration)
    }

    target(node) {
        this.tree.calls++
        const declaration = this.checker.getResolvedSignature(node)?.declaration
        if (!declaration || ts.isJSDocSignature(declaration)) return undefined
        const symbol = this.idOf(declaration)
        if (!symbol) return undefined
        this.tree.resolved++
        const target = { symbol, name: declaredName(declaration) ?? (ts.isConstructSignatureDeclaration(declaration) ? 'new' : 'constructor') }
        const container = declaration.parent
        if (container && (ts.isInterfaceDeclaration(container) || ts.isClassDeclaration(container))) {
            const type = this.idOf(container)
            if (type) target.type = type
        }

        return target
    }

    typeOf(type, origin, depth = 0) {
        const key = `${origin}:${depth}`
        const known = this.program.described.get(type)?.get(key)
        if (known) return known
        const out = this.describe(type, origin, depth)
        if (!this.program.described.has(type)) this.program.described.set(type, new Map())
        this.program.described.get(type).set(key, out)

        return out
    }

    /** A type as the contract writes it, described afresh: its text, its shape to TYPE_DEPTH, and where it came from. */
    describe(type, origin, depth) {
        const out = { text: this.program.printed(type) }
        Object.assign(out, depth >= TYPE_DEPTH ? { kind: 'opaque' } : this.shape(type, origin, depth + 1))
        out.nullable = nullable(type)
        out.origin = origin

        return out
    }

    /**
     * A property's name as the code writes it. A key that is a unique symbol, or a private name, is held by TypeScript
     * under a name carrying an id of the order it checked in (`__@Marker@37836`), so it is written as the code spells it.
     */
    fieldName(property) {
        const held = String(property.escapedName)

        return held.startsWith('__@') || held.startsWith('__#') ? this.checker.symbolToString(property) : property.getName()
    }

    shape(type, origin, depth) {
        if (type.isUnion()) return { kind: 'union', members: type.types.map((member) => this.typeOf(member, origin, depth)) }
        if (type.isIntersection()) return { kind: 'intersection', members: type.types.map((member) => this.typeOf(member, origin, depth)) }
        if (type.isStringLiteral()) return { kind: 'literal', value: type.value }
        if (type.isNumberLiteral()) return { kind: 'literal', value: String(type.value) }
        if (type.flags & ts.TypeFlags.BooleanLiteral) return { kind: 'literal', value: this.checker.typeToString(type) === 'true' }
        if (type.isTypeParameter()) return { kind: 'parameter', name: this.checker.typeToString(type) }
        if (type.flags & KEYWORD_TYPES) return { kind: 'keyword', name: this.checker.typeToString(type) }
        if (this.checker.isArrayType(type)) return { kind: 'array', args: [this.typeOf(this.checker.getTypeArguments(type)[0], origin, depth)] }
        if (this.checker.isTupleType(type)) return { kind: 'tuple', members: this.checker.getTypeArguments(type).map((member) => this.typeOf(member, origin, depth)) }
        if (!(type.flags & ts.TypeFlags.Object)) return { kind: 'opaque' }
        const declaration = (type.aliasSymbol ?? type.getSymbol())?.declarations?.[0]
        const name = declaration && namesItsScope(declaration) ? this.idOf(declaration) : undefined
        if (name) {
            const args = type.aliasTypeArguments ?? (type.objectFlags & ts.ObjectFlags.Reference ? this.checker.getTypeArguments(type) : [])
            const out = { kind: 'named', name }
            if (args.length) out.args = args.map((argument) => this.typeOf(argument, origin, depth))

            return out
        }
        const signatures = type.getCallSignatures()
        if (signatures.length === 1 && !type.getProperties().length) {
            const signature = signatures[0]

            return {
                kind: 'function',
                parameters: signature.getParameters().map((parameter) => this.typeOf(this.checker.getTypeOfSymbol(parameter), origin, depth)),
                returns: this.typeOf(signature.getReturnType(), origin, depth),
            }
        }
        if (type.objectFlags & ts.ObjectFlags.Anonymous || type.objectFlags & ts.ObjectFlags.Mapped) {
            // A shape the project itself declares nowhere is not the project's: `typeof globalThis` holds every
            // ambient name there is, a thousand fields deep in itself, each printed as however many declaration
            // files have merged into it, so describing it says nothing about the code and makes a file's tree
            // answer for the whole program. An ambient shape is written as what it is, a type of its own; every
            // shape the code writes — an object literal, a mapped type, a spread, an inferred return — is declared
            // in a file the project wrote, so it is described as before.
            if (ambient(type)) return { kind: 'opaque' }

            return this.shapeOf(type, origin, depth)
        }

        return { kind: 'opaque' }
    }
}

/**
 * Whether a type is declared outside the code the project wrote: nothing declares it, or every declaration of it
 * stands in a declaration file. `typeof globalThis` is both in turn — it carries no declaration of its own until a
 * package declares into the global scope, and then carries only theirs.
 */
function ambient(type) {
    const declarations = (type.aliasSymbol ?? type.getSymbol())?.declarations ?? []

    return declarations.length === 0 || declarations.every((each) => each.getSourceFile().isDeclarationFile)
}

/** Whether `node` is a declaration the contract gives a symbol id: a type, a function, a member, a module-level variable. */
function declaresSymbol(node) {
    if (MEMBERS.has(node.kind)) return declaredName(node) !== undefined || isDefaultExport(node)
    if (ts.isParameter(node) && ts.isParameterPropertyDeclaration(node, node.parent)) return true
    if (ts.isVariableDeclaration(node)) return ts.isIdentifier(node.name) && ts.isVariableDeclarationList(node.parent)
        && ts.isVariableStatement(node.parent.parent) && ts.isSourceFile(node.parent.parent.parent)
    if (ts.isConstructSignatureDeclaration(node)) return true

    return false
}

/** Whether `node` is one of the names a symbol id is made of. */
function namesItsScope(node) {
    return MEMBERS.has(node.kind) || declaresSymbol(node)
}

function declaredName(node) {
    if (node.kind === ts.SyntaxKind.Constructor) return 'constructor'
    if (node.name && (ts.isIdentifier(node.name) || ts.isPrivateIdentifier(node.name) || ts.isStringLiteral(node.name))) return node.name.text === '' ? undefined : node.name.text
    if (isDefaultExport(node)) return 'default'

    return undefined
}

function isDefaultExport(node) {
    return (ts.isFunctionDeclaration(node) || ts.isClassDeclaration(node)) && !node.name
        && (ts.getModifiers(node) ?? []).some((modifier) => modifier.kind === ts.SyntaxKind.DefaultKeyword)
}

function isDeclarationName(node) {
    return node.parent && ts.isDeclaration(node.parent) && node.parent.name === node
        && !ts.isShorthandPropertyAssignment(node.parent)
}

/** Whether an identifier is a bare name read: not a member name after a dot, not a property key, not a label. */
function isNameRead(node) {
    const parent = node.parent
    if (!parent || isDeclarationName(node)) return false
    if (ts.isPropertyAccessExpression(parent) && parent.name === node) return false
    if (ts.isQualifiedName(parent) && parent.right === node) return false
    if (ts.isPropertyAssignment(parent) && parent.name === node) return false
    if ((ts.isLabeledStatement(parent) || ts.isBreakOrContinueStatement(parent)) && parent.label === node) return false
    if (ts.isImportSpecifier(parent) || ts.isExportSpecifier(parent) || ts.isImportClause(parent) || ts.isNamespaceImport(parent)) return false
    if (ts.isJsxAttribute(parent) || ts.isMetaProperty(parent)) return false

    return true
}

function isImportBinding(node) {
    const parent = node.parent

    return parent && (ts.isImportSpecifier(parent) || ts.isImportClause(parent) || ts.isNamespaceImport(parent)) && parent.name === node
}

/** Whether the checker gives `node` a type the contract writes as `resolved`: an expression, never a type or a declaration's name. */
function typed(node) {
    if (!ts.isExpression(node) && !ts.isIdentifier(node)) return false
    if (ts.isTypeNode(node) || ts.isOmittedExpression(node)) return false
    if (ts.isIdentifier(node) && !isNameRead(node)) return false

    return true
}

function nullable(type) {
    if (type.flags & (ts.TypeFlags.Null | ts.TypeFlags.Undefined)) return true

    return type.isUnion() && type.types.some((member) => member.flags & (ts.TypeFlags.Null | ts.TypeFlags.Undefined))
}

function isErrorType(type) {
    return type.intrinsicName === 'error'
}

/** Whether a top-level statement declares: a function, a class, an interface, an enum, a type alias or a namespace. */
function declares(statement) {
    return ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement) || ts.isInterfaceDeclaration(statement)
        || ts.isEnumDeclaration(statement) || ts.isTypeAliasDeclaration(statement) || ts.isModuleDeclaration(statement)
}


function kindName(node) {
    return ts.SyntaxKind[node.kind].replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()
}

/**
 * What TypeScript's parser makes of a comment's words read as statements; the shared rule decides whether they are code.
 * A lone expression's kind is what the translation makes of it, as for any expression of the file.
 */
export function fragment(raw) {
    const words = raw.replace(/^(?:\/\/|\/\*+)/, '').replace(/\*\/$/, '').trim()
    if (!words) return { context: 'statement', diagnostics: 0, statements: 0 }
    const parsed = ts.createSourceFile('comment.ts', words, ts.ScriptTarget.ESNext, true)
    const read = { context: 'statement', diagnostics: parsed.parseDiagnostics.length, statements: parsed.statements.length }
    const [lone] = parsed.statements
    if (parsed.statements.length === 1 && ts.isExpressionStatement(lone)) {
        read.expressionKind = new Translator(new Tree(new Source('comment.ts', words)), parsed, 0, undefined).expression(lone.expression).kind
    }

    return read
}
