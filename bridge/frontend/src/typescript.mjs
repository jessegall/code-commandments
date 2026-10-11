import ts from 'typescript'

/** The name TypeScript gives every SyntaxKind, its markers (FirstX, LastX) left out. */
export const KINDS = {}
for (const [name, value] of Object.entries(ts.SyntaxKind)) {
    if (typeof value === 'number' && !/^(First|Last)/.test(name) && !(value in KINDS)) KINDS[value] = name
}

/** Declarations the contract gives role `member`, and a symbol id. */
const MEMBERS = new Set([
    ts.SyntaxKind.FunctionDeclaration, ts.SyntaxKind.ClassDeclaration, ts.SyntaxKind.InterfaceDeclaration,
    ts.SyntaxKind.TypeAliasDeclaration, ts.SyntaxKind.EnumDeclaration, ts.SyntaxKind.ModuleDeclaration,
    ts.SyntaxKind.MethodDeclaration, ts.SyntaxKind.PropertyDeclaration, ts.SyntaxKind.PropertySignature,
    ts.SyntaxKind.MethodSignature, ts.SyntaxKind.Constructor, ts.SyntaxKind.GetAccessor, ts.SyntaxKind.SetAccessor,
    ts.SyntaxKind.EnumMember,
])

/** Properties of a node that are not child slots: bookkeeping, and tokens, which the contract never makes nodes. */
const SKIPPED_KEYS = new Set(['parent', 'pos', 'end', 'flags', 'kind', 'modifierFlagsCache', 'transformFlags', 'jsDoc', 'symbol',
    'localSymbol', 'locals', 'nextContainer', 'flowNode', 'emitNode', 'id', 'original', 'modifiers', 'questionToken',
    'dotDotDotToken', 'asteriskToken', 'exclamationToken', 'equalsGreaterThanToken', 'operatorToken', 'questionDotToken',
    'endFlowNode', 'returnFlowNode', 'nextToken', 'endOfFileToken', 'awaitModifier', 'colonToken', 'equalsToken',
    'readonlyToken'])

const COMPARISONS = new Set([ts.SyntaxKind.EqualsEqualsEqualsToken, ts.SyntaxKind.ExclamationEqualsEqualsToken,
    ts.SyntaxKind.EqualsEqualsToken, ts.SyntaxKind.ExclamationEqualsToken, ts.SyntaxKind.LessThanToken,
    ts.SyntaxKind.GreaterThanToken, ts.SyntaxKind.LessThanEqualsToken, ts.SyntaxKind.GreaterThanEqualsToken])

const KEYWORD_TYPES = ts.TypeFlags.String | ts.TypeFlags.Number | ts.TypeFlags.Boolean | ts.TypeFlags.Void
    | ts.TypeFlags.Undefined | ts.TypeFlags.Null | ts.TypeFlags.Unknown | ts.TypeFlags.Never | ts.TypeFlags.BigInt
    | ts.TypeFlags.ESSymbol | ts.TypeFlags.NonPrimitive

/** How deep a type's structure is written before the rest is left opaque. */
const TYPE_DEPTH = 3

/**
 * Writes TypeScript nodes as the contract's nodes. `offset` is where the parsed text starts in the file, in UTF-16
 * units; the program's checker is there for script files and absent for template expressions, which no checker types.
 */
export class TypeScriptWriter {
    constructor(tree, sourceFile, offset, program) {
        this.tree = tree
        this.sourceFile = sourceFile
        this.offset = offset
        this.program = program
        this.checker = program?.checker
    }

    node(node, field) {
        const start = this.offset + node.getStart(this.sourceFile)
        const end = this.offset + node.end
        const out = { id: this.tree.number(start, end), kind: KINDS[node.kind], role: role(node, field) }
        const is = neutral(node)
        if (is.length) out.is = is
        out.span = this.tree.source.span(start, end)
        if (field) out.field = field
        Object.assign(out, this.facts(node))
        const children = []
        ts.forEachChild(node, (child) => {
            const slot = fieldOf(node, child)
            if (slot) children.push(this.node(child, slot))
        })
        if (children.length) out.children = children

        return out
    }

    /** The statements of `sourceFile` that fall inside `[start, end)`, as one SourceFile node spanning that range. */
    part(start, end, field) {
        const out = { id: this.tree.number(start, end), kind: 'SourceFile', role: 'other', span: this.tree.source.span(start, end), field }
        const children = this.sourceFile.statements
            .filter((statement) => statement.getStart(this.sourceFile) >= start && statement.end <= end)
            .map((statement) => this.node(statement, 'statements'))
        if (children.length) out.children = children

        return out
    }

    facts(node) {
        const facts = {}
        const name = nameOf(node)
        if (name !== undefined) facts.name = name
        Object.assign(facts, literal(node))
        if (ts.isBinaryExpression(node)) facts.operator = ts.tokenToString(node.operatorToken.kind)
        if (ts.isPrefixUnaryExpression(node) || ts.isPostfixUnaryExpression(node)) facts.operator = ts.tokenToString(node.operator)
        if (ts.isTypeOfExpression(node)) facts.operator = 'typeof'
        if (ts.isAwaitExpression(node)) facts.operator = 'await'
        if (ts.isDeleteExpression(node)) facts.operator = 'delete'
        if (ts.isVoidExpression(node)) facts.operator = 'void'
        if (ts.isHeritageClause(node)) facts.operator = ts.tokenToString(node.token)
        const modifiers = (ts.canHaveModifiers(node) ? ts.getModifiers(node) : undefined) ?? []
        if (modifiers.length) facts.modifiers = modifiers.map((modifier) => ts.tokenToString(modifier.kind))
        const flags = flagsOf(node, modifiers)
        if (flags.length) facts.flags = flags
        if (ts.isImportDeclaration(node) && node.importClause?.isTypeOnly) facts.extras = { typescript: { typeOnly: true } }
        if (!this.checker) return facts

        if (declaresType(node) && node.type) facts.declared = this.type(this.checker.getTypeFromTypeNode(node.type), 'written')
        if (ts.isCatchClause(node) && node.variableDeclaration?.type) {
            facts.declared = this.type(this.checker.getTypeFromTypeNode(node.variableDeclaration.type), 'written')
        }
        if (ts.isFunctionLike(node) && node.type) facts.returns = this.type(this.checker.getTypeFromTypeNode(node.type), 'written')
        const symbol = this.symbolOf(node)
        if (symbol) facts.symbol = symbol
        if (this.tree.checked && typed(node)) {
            this.tree.expressions++
            const type = this.checker.getTypeAtLocation(node)
            if (!(type.flags & ts.TypeFlags.Any) && !isErrorType(type)) {
                this.tree.typed++
                facts.resolvedType = this.tree.typeIndex(type, () => this.type(type, 'compiler'))
            }
        }
        const refers = this.refersOf(node)
        if (refers) facts.refers = refers
        if (ts.isCallExpression(node) || ts.isNewExpression(node) || ts.isDecorator(node)) Object.assign(facts, this.target(node))
        if (ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) {
            const resolves = node.moduleSpecifier && ts.isStringLiteral(node.moduleSpecifier) ? this.program.resolve(node.moduleSpecifier.text, this.sourceFile.fileName) : undefined
            if (resolves) facts.resolves = resolves
        }

        return facts
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
        if (!ts.isIdentifier(node) || isDeclarationName(node)) return undefined
        if (!(isNameRead(node) || isImportBinding(node))) return undefined
        let symbol = this.checker.getSymbolAtLocation(node)
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
        if (!declaration || ts.isJSDocSignature(declaration)) return {}
        const symbol = this.idOf(declaration)
        if (!symbol) return {}
        this.tree.resolved++
        const target = { symbol, name: declaredName(declaration) ?? (ts.isConstructSignatureDeclaration(declaration) ? 'new' : 'constructor') }
        const container = declaration.parent
        if (container && (ts.isInterfaceDeclaration(container) || ts.isClassDeclaration(container))) {
            const type = this.idOf(container)
            if (type) target.type = type
        }

        return { target }
    }

    type(type, origin, depth = 0) {
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
        if (nullable(type)) out.nullable = true
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
        if (type.isUnion()) return { kind: 'union', members: type.types.map((member) => this.type(member, origin, depth)) }
        if (type.isIntersection()) return { kind: 'intersection', members: type.types.map((member) => this.type(member, origin, depth)) }
        if (type.isStringLiteral()) return { kind: 'literal', value: type.value }
        if (type.isNumberLiteral()) return { kind: 'literal', value: String(type.value) }
        if (type.flags & ts.TypeFlags.BooleanLiteral) return { kind: 'literal', value: this.checker.typeToString(type) === 'true' }
        if (type.isTypeParameter()) return { kind: 'parameter', name: this.checker.typeToString(type) }
        if (type.flags & KEYWORD_TYPES) return { kind: 'keyword', name: this.checker.typeToString(type) }
        if (this.checker.isArrayType(type)) return { kind: 'array', args: [this.type(this.checker.getTypeArguments(type)[0], origin, depth)] }
        if (this.checker.isTupleType(type)) return { kind: 'tuple', members: this.checker.getTypeArguments(type).map((member) => this.type(member, origin, depth)) }
        if (!(type.flags & ts.TypeFlags.Object)) return { kind: 'opaque' }
        const declaration = (type.aliasSymbol ?? type.getSymbol())?.declarations?.[0]
        const name = declaration && namesItsScope(declaration) ? this.idOf(declaration) : undefined
        if (name) {
            const args = type.aliasTypeArguments ?? (type.objectFlags & ts.ObjectFlags.Reference ? this.checker.getTypeArguments(type) : [])
            const out = { kind: 'named', name }
            if (args.length) out.args = args.map((argument) => this.type(argument, origin, depth))

            return out
        }
        const signatures = type.getCallSignatures()
        if (signatures.length === 1 && !type.getProperties().length) {
            const signature = signatures[0]

            return {
                kind: 'function',
                parameters: signature.getParameters().map((parameter) => this.type(this.checker.getTypeOfSymbol(parameter), origin, depth)),
                returns: this.type(signature.getReturnType(), origin, depth),
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
            const fields = type.getProperties().map((property) => {
                const field = { name: this.fieldName(property), type: this.type(this.checker.getTypeOfSymbol(property), origin, depth) }
                if (property.flags & ts.SymbolFlags.Optional) field.optional = true

                return field
            })

            return { kind: 'object', fields }
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

/** The slot `child` fills in `parent`; none for a token, which the contract never makes a node. */
function fieldOf(parent, child) {
    for (const [key, value] of Object.entries(parent)) {
        if (value !== child && !(Array.isArray(value) && value.includes(child))) continue
        if (key === 'modifiers' && ts.isDecorator(child)) return key
        if (SKIPPED_KEYS.has(key)) return undefined

        return key
    }
    throw new Error(`${KINDS[child.kind]} fills no field of ${KINDS[parent.kind]}`)
}

function role(node, field) {
    if (MEMBERS.has(node.kind)) return 'member'
    if (ts.isTypeNode(node)) return 'type'
    if (ts.isObjectBindingPattern(node) || ts.isArrayBindingPattern(node)) return 'pattern'
    if (ts.isStatement(node) || ts.isImportDeclaration(node)) return 'statement'
    if (field === 'name' && ts.isIdentifier(node)) return 'other'
    if (ts.isExpression(node) || ts.isIdentifier(node)) return 'expression'

    return 'other'
}

function neutral(node) {
    const answers = {
        function: ts.isFunctionLike(node) && node.body !== undefined,
        'type-declaration': ts.isClassDeclaration(node) || ts.isClassExpression(node) || ts.isInterfaceDeclaration(node)
            || ts.isTypeAliasDeclaration(node) || ts.isEnumDeclaration(node),
        parameter: ts.isParameter(node),
        block: ts.isBlock(node) || ts.isModuleBlock(node),
        branch: ts.isIfStatement(node) || ts.isSwitchStatement(node) || ts.isConditionalExpression(node),
        loop: ts.isIterationStatement(node, false),
        return: ts.isReturnStatement(node),
        throw: ts.isThrowStatement(node),
        'bail-out': ts.isReturnStatement(node) || ts.isThrowStatement(node) || ts.isBreakOrContinueStatement(node),
        'expression-statement': ts.isExpressionStatement(node),
        call: ts.isCallExpression(node),
        construction: ts.isNewExpression(node),
        'member-access': ts.isPropertyAccessExpression(node) || ts.isElementAccessExpression(node),
        'null-safe': (ts.isPropertyAccessExpression(node) || ts.isCallExpression(node) || ts.isElementAccessExpression(node))
            && node.questionDotToken !== undefined,
        'self-reference': node.kind === ts.SyntaxKind.ThisKeyword,
        identifier: ts.isIdentifier(node) && isNameRead(node),
        assignment: ts.isBinaryExpression(node) && node.operatorToken.kind >= ts.SyntaxKind.FirstAssignment
            && node.operatorToken.kind <= ts.SyntaxKind.LastAssignment,
        comparison: ts.isBinaryExpression(node) && COMPARISONS.has(node.operatorToken.kind),
        literal: ts.isLiteralExpression(node) || ts.isTemplateExpression(node) || ts.isNoSubstitutionTemplateLiteral(node)
            || [ts.SyntaxKind.TrueKeyword, ts.SyntaxKind.FalseKeyword, ts.SyntaxKind.NullKeyword].includes(node.kind),
        import: ts.isImportDeclaration(node) || ts.isImportEqualsDeclaration(node),
        catch: ts.isCatchClause(node),
    }

    return Object.keys(answers).filter((name) => answers[name])
}

function nameOf(node) {
    if (ts.isIdentifier(node) || ts.isPrivateIdentifier(node)) return written(node.text)
    if (node.kind === ts.SyntaxKind.Constructor) return 'constructor'
    const name = node.name
    if (name && (ts.isIdentifier(name) || ts.isPrivateIdentifier(name) || ts.isStringLiteral(name) || ts.isNumericLiteral(name))) return written(name.text)

    return undefined
}

/** A name as written, or undefined for an empty one: the key `""`, or the identifier a parse error left missing. */
function written(text) {
    return text === '' ? undefined : text
}

function literal(node) {
    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) return { literal: 'string', value: node.text }
    if (ts.isNumericLiteral(node)) return { literal: /[.eE]/.test(node.text) && !/^0[xob]/i.test(node.text) ? 'float' : 'int', value: String(Number(node.text)) }
    if (ts.isBigIntLiteral(node)) return { literal: 'int', value: node.text.slice(0, -1) }
    if (ts.isTemplateExpression(node)) return { literal: 'interpolated' }
    if (node.kind === ts.SyntaxKind.TrueKeyword || node.kind === ts.SyntaxKind.FalseKeyword) return { literal: 'bool', value: node.kind === ts.SyntaxKind.TrueKeyword }
    if (node.kind === ts.SyntaxKind.NullKeyword) return { literal: 'null', value: null }
    if (ts.isIdentifier(node) && node.text === 'undefined' && isNameRead(node)) return { literal: 'undefined' }

    return {}
}

function flagsOf(node, modifiers) {
    const flags = []
    if (ts.isParameter(node) && node.dotDotDotToken) flags.push('variadic')
    if ((ts.isParameter(node) || ts.isPropertySignature(node) || ts.isPropertyDeclaration(node) || ts.isMethodSignature(node)
        || ts.isMethodDeclaration(node)) && node.questionToken) flags.push('optional')
    if (ts.isSpreadElement(node) || ts.isSpreadAssignment(node) || (ts.isBindingElement(node) && node.dotDotDotToken)) flags.push('spread')
    if (modifiers.some((modifier) => modifier.kind === ts.SyntaxKind.AsyncKeyword)) flags.push('async')
    if (ts.isFunctionLike(node) && node.asteriskToken) flags.push('generator')

    return flags
}

/** Declarations that take the contract's `declared`: a parameter, property, field or variable with a written type. */
function declaresType(node) {
    return ts.isParameter(node) || ts.isPropertySignature(node) || ts.isPropertyDeclaration(node) || ts.isVariableDeclaration(node)
}

/** Whether `node` is a declaration the contract gives a symbol id: a type, a function, a member, a module-level variable. */
function declaresSymbol(node) {
    if (MEMBERS.has(node.kind)) return declaredName(node) !== undefined || isDefaultExport(node)
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
    if (node.name && (ts.isIdentifier(node.name) || ts.isPrivateIdentifier(node.name) || ts.isStringLiteral(node.name))) return written(node.name.text)
    if (isDefaultExport(node)) return 'default'

    return undefined
}

function isDefaultExport(node) {
    return (ts.isFunctionDeclaration(node) || ts.isClassDeclaration(node)) && !node.name
        && (ts.getModifiers(node) ?? []).some((modifier) => modifier.kind === ts.SyntaxKind.DefaultKeyword)
}

function isDeclarationName(node) {
    return node.parent && ts.isDeclaration(node.parent) && node.parent.name === node
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
