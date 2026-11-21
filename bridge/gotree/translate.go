package gotree

import (
	"go/ast"
	"go/constant"
	"go/token"
	"go/types"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// writer translates one Go file into the version 5 generic tree (contract/CONTRACT.md): one neutral kind per
// operation, its parts under named roles, its facts, and byte spans into the file. Compiler facts come from go/types
// and are left out when the package was not checked.
type writer struct {
	fset     *token.FileSet
	source   []byte
	facts    *types.Info
	module   string
	owners   map[*ast.Ident]string
	types    map[types.Type]*contract.Type
	links    []pendingLink
	imported map[string]bool
	declared []declaredType
}

// pendingLink is a link whose target nodes have no ids until the tree is numbered.
type pendingLink struct {
	from *contract.Node
	name string
	to   []*contract.Node
	list bool
}

// declaredType is a type the file declares, kept to link what it satisfies once every declaration is read.
type declaredType struct {
	node   *contract.Node
	object *types.TypeName
}

var comparisons = map[token.Token]string{
	token.EQL: "equal", token.NEQ: "not-equal", token.LSS: "less", token.LEQ: "less-or-equal", token.GTR: "greater", token.GEQ: "greater-or-equal",
}

var binaries = map[token.Token]string{
	token.ADD: "add", token.SUB: "subtract", token.MUL: "multiply", token.QUO: "divide", token.REM: "modulo", token.AND: "bit-and",
	token.OR: "bit-or", token.XOR: "bit-xor", token.SHL: "shift-left", token.SHR: "shift-right", token.AND_NOT: "bit-and-not",
}

var compounds = map[token.Token]string{
	token.ADD_ASSIGN: "add", token.SUB_ASSIGN: "subtract", token.MUL_ASSIGN: "multiply", token.QUO_ASSIGN: "divide", token.REM_ASSIGN: "modulo",
	token.AND_ASSIGN: "bit-and", token.OR_ASSIGN: "bit-or", token.XOR_ASSIGN: "bit-xor", token.SHL_ASSIGN: "shift-left", token.SHR_ASSIGN: "shift-right",
	token.AND_NOT_ASSIGN: "bit-and-not",
}

var unaries = map[token.Token]string{token.NOT: "not", token.SUB: "negate", token.ADD: "plus", token.XOR: "bit-not", token.AND: "reference-of", token.ARROW: "receive"}

// Positions -------------------------------------------------------------------------------------------------------

func (w *writer) between(kind string, from, to token.Pos) *contract.Node {
	start := w.fset.Position(from)
	end := start.Offset
	if to.IsValid() {
		end = max(end, w.fset.Position(to).Offset)
	}

	return contract.NewNode(contract.Node{Kind: kind, Span: contract.Span{Start: start.Offset, End: end, Line: start.Line}})
}

func (w *writer) at(kind string, held ast.Node) *contract.Node {
	return w.between(kind, held.Pos(), held.End())
}

func hold(node *contract.Node, role string, child *contract.Node) *contract.Node {
	if child != nil {
		node.Parts = append(node.Parts, contract.Part{Role: role, Nodes: []*contract.Node{child}})
	}

	return node
}

func holdAll(node *contract.Node, role string, children []*contract.Node) *contract.Node {
	children = slices.DeleteFunc(children, func(child *contract.Node) bool { return child == nil })
	if len(children) > 0 {
		node.Parts = append(node.Parts, contract.Part{Role: role, Nodes: children, List: true})
	}

	return node
}

func (w *writer) link(from *contract.Node, name string, to []*contract.Node, list bool) {
	w.links = append(w.links, pendingLink{from: from, name: name, to: to, list: list})
}

func (w *writer) binding(name *ast.Ident) *contract.Node {
	node := w.at("binding", name)
	node.Own().Name = name.Name
	if w.facts != nil {
		node.Own().Symbol = w.symbol(name)
	}

	return node
}

func (w *writer) reference(name *ast.Ident) *contract.Node {
	node := w.at("reference", name)
	node.Own().Name, node.Own().Form = name.Name, "name"
	if w.facts != nil {
		node.Own().Refers = w.refers(name)
	}

	return node
}

func visibility(name string) *contract.Effective {
	if ast.IsExported(name) {
		return &contract.Effective{Visibility: "public"}
	}

	return &contract.Effective{Visibility: "package"}
}

// The file --------------------------------------------------------------------------------------------------------

func (w *writer) file(parsed *ast.File) *contract.Node {
	whole := len(w.source)
	root := contract.NewNode(contract.Node{Kind: "file", Span: contract.Span{Start: 0, End: whole, Line: 1}})
	module := contract.NewNode(contract.Node{Kind: "module", Span: contract.Span{Start: 0, End: whole, Line: 1}})
	module.Own().Name = parsed.Name.Name
	var imports, declarations []*contract.Node
	for index, declared := range parsed.Decls {
		switch held := declared.(type) {
		case *ast.FuncDecl:
			function := w.function(held)
			if !held.Type.Params.Closing.IsValid() || function.Span.End <= function.Span.Start {
				until := whole
				if index+1 < len(parsed.Decls) {
					until = w.fset.Position(parsed.Decls[index+1].Pos()).Offset
				}
				w.recovered(function, until)
			}
			declarations = append(declarations, function)
		case *ast.GenDecl:
			for _, spec := range held.Specs {
				span := ast.Node(spec)
				if !held.Lparen.IsValid() {
					span = held
				}
				switch written := spec.(type) {
				case *ast.ImportSpec:
					imports = append(imports, w.importOf(written, span))
				case *ast.TypeSpec:
					declarations = append(declarations, w.typeOf(written, span))
				case *ast.ValueSpec:
					declarations = append(declarations, w.value(written, span, held.Tok))
				}
			}
		case *ast.BadDecl:
			bad := w.at("unsupported", held)
			bad.Own().Construct, bad.Own().Incomplete = "declaration", true
			declarations = append(declarations, bad)
		}
	}
	holdAll(module, "imports", imports)
	holdAll(module, "declarations", declarations)
	w.satisfies()

	return holdAll(root, "modules", []*contract.Node{module})
}

// recovered marks a function the parser lost the end of (its parameter list never closed): it reaches the code written
// up to the next declaration, and its roles are unknown rather than empty.
func (w *writer) recovered(function *contract.Node, until int) {
	function.Own().Incomplete = true
	if function.Span.End <= function.Span.Start {
		function.Span.End = function.Span.Start + len(strings.TrimRight(string(w.source[function.Span.Start:until]), " \t\r\n"))
	}
}

func (w *writer) importOf(spec *ast.ImportSpec, span ast.Node) *contract.Node {
	path, _ := strconv.Unquote(spec.Path.Value)
	node := w.at("import", span)
	node.Own().Source, node.Own().Form = path, "module"
	if spec.Name != nil {
		switch spec.Name.Name {
		case "_":
			node.Own().Form = "side-effect"
		case ".":
			node.Own().Form = "wildcard"
		default:
			hold(node, "alias", w.binding(spec.Name))
			w.imported[spec.Name.Name] = true
		}
	} else {
		w.imported[lastSegment(path)] = true
	}
	if w.facts != nil {
		if name := w.facts.PkgNameOf(spec); name != nil {
			node.Own().Refers = name.Imported().Path()
			w.imported[name.Name()] = true
		}
	}

	return node
}

func lastSegment(path string) string {
	for at := len(path) - 1; at >= 0; at-- {
		if path[at] == '/' {
			return path[at+1:]
		}
	}

	return path
}

// Declarations ----------------------------------------------------------------------------------------------------

func (w *writer) typeOf(spec *ast.TypeSpec, span ast.Node) *contract.Node {
	if spec.Assign.IsValid() || !isStructOrInterface(spec.Type) {
		alias := w.at("alias", span)
		alias.Own().Name, alias.Own().Effective = spec.Name.Name, visibility(spec.Name.Name)
		if w.facts != nil {
			alias.Own().Symbol = w.symbol(spec.Name)
		}
		holdAll(alias, "typeParameters", w.typeParameters(spec.TypeParams))

		return hold(alias, "underlyingType", w.typeExpression(spec.Type))
	}
	node := w.at("type", span)
	node.Own().Name, node.Own().Effective = spec.Name.Name, visibility(spec.Name.Name)
	if w.facts != nil {
		node.Own().Symbol = w.symbol(spec.Name)
	}
	holdAll(node, "typeParameters", w.typeParameters(spec.TypeParams))
	var bases, members []*contract.Node
	switch shape := spec.Type.(type) {
	case *ast.InterfaceType:
		node.Own().Form = "interface"
		for _, method := range shape.Methods.List {
			signature, isMethod := method.Type.(*ast.FuncType)
			if !isMethod || len(method.Names) == 0 {
				bases = append(bases, w.typeExpression(method.Type))
				continue
			}
			for _, name := range method.Names {
				members = append(members, w.signature(w.at("function", method), name, "method", nil, signature, nil))
			}
		}
	case *ast.StructType:
		node.Own().Form = "struct"
		for _, field := range shape.Fields.List {
			members = append(members, w.field(field))
		}
	}
	holdAll(node, "bases", bases)
	holdAll(node, "writtenMembers", members)
	if w.facts != nil {
		if object, defined := w.facts.Defs[spec.Name].(*types.TypeName); defined {
			w.declared = append(w.declared, declaredType{node: node, object: object})
		}
	}

	return node
}

func isStructOrInterface(written ast.Expr) bool {
	switch written.(type) {
	case *ast.StructType, *ast.InterfaceType:
		return true
	}

	return false
}

// satisfies links each type the file declares to every interface of the file it implements, by value or by pointer.
func (w *writer) satisfies() {
	for _, implementer := range w.declared {
		if types.IsInterface(implementer.object.Type()) {
			continue
		}
		var satisfied []*contract.Node
		for _, other := range w.declared {
			face, isInterface := other.object.Type().Underlying().(*types.Interface)
			if !isInterface || face.Empty() {
				continue
			}
			held := implementer.object.Type()
			if types.Implements(held, face) || types.Implements(types.NewPointer(held), face) {
				satisfied = append(satisfied, other.node)
			}
		}
		if len(satisfied) > 0 {
			w.link(implementer.node, "satisfies", satisfied, true)
		}
	}
}

func (w *writer) field(field *ast.Field) *contract.Node {
	node := w.at("field", field)
	hold(node, "declaredType", w.typeExpression(field.Type))
	if len(field.Names) == 0 {
		return holdAll(node, "declarators", []*contract.Node{w.at("declarator", field.Type)})
	}
	var declarators []*contract.Node
	for _, name := range field.Names {
		declarator := w.at("declarator", name)
		hold(declarator, "binding", w.binding(name))
		declarators = append(declarators, declarator)
	}
	if len(field.Names) == 1 {
		node.Own().Effective = visibility(field.Names[0].Name)
	}

	return holdAll(node, "declarators", declarators)
}

func (w *writer) value(spec *ast.ValueSpec, span ast.Node, tok token.Token) *contract.Node {
	kind := "variable"
	if tok == token.CONST {
		kind = "constant"
	}
	node := w.at(kind, span)
	if kind == "variable" {
		node.Own().Form = "var"
	}
	if spec.Type != nil {
		hold(node, "declaredType", w.typeExpression(spec.Type))
	}
	var declarators []*contract.Node
	for at, name := range spec.Names {
		end := name.End()
		if at < len(spec.Values) {
			end = spec.Values[at].End()
		}
		declarator := w.between("declarator", name.Pos(), end)
		hold(declarator, "binding", w.binding(name))
		if at < len(spec.Values) {
			hold(declarator, "initializer", w.expression(spec.Values[at]))
		}
		declarators = append(declarators, declarator)
	}

	return holdAll(node, "declarators", declarators)
}

func (w *writer) function(declared *ast.FuncDecl) *contract.Node {
	form := "function"
	var receiver *ast.Field
	if declared.Recv != nil && len(declared.Recv.List) > 0 {
		form, receiver = "method", declared.Recv.List[0]
	}
	node := w.signature(w.at("function", declared), declared.Name, form, receiver, declared.Type, declared.Body)
	holdAll(node, "typeParameters", w.typeParameters(declared.Type.TypeParams))

	return node
}

func (w *writer) signature(node *contract.Node, name *ast.Ident, form string, receiver *ast.Field, signature *ast.FuncType, body *ast.BlockStmt) *contract.Node {
	if name != nil {
		node.Own().Name, node.Own().Effective = name.Name, visibility(name.Name)
		if w.facts != nil {
			node.Own().Symbol = w.symbol(name)
		}
	}
	node.Own().Form = form
	if receiver != nil {
		hold(node, "receiver", w.parameters(receiver)[0])
	}
	if signature.Params != nil {
		var parameters []*contract.Node
		for _, field := range signature.Params.List {
			parameters = append(parameters, w.parameters(field)...)
		}
		holdAll(node, "parameters", parameters)
	}
	hold(node, "returnType", w.results(signature.Results))
	if body != nil {
		hold(node, "body", w.block(body))
	}

	return node
}

func (w *writer) parameters(field *ast.Field) []*contract.Node {
	written, collects := field.Type, ""
	if ellipsis, variadic := written.(*ast.Ellipsis); variadic {
		written, collects = ellipsis.Elt, "rest"
	}
	if len(field.Names) == 0 {
		node := w.at("parameter", field)
		node.Own().Collects = collects

		return []*contract.Node{hold(node, "declaredType", w.typeExpression(written))}
	}
	var parameters []*contract.Node
	for _, name := range field.Names {
		node := w.between("parameter", name.Pos(), field.End())
		node.Own().Collects = collects
		hold(node, "binding", w.binding(name))
		parameters = append(parameters, hold(node, "declaredType", w.typeExpression(written)))
	}

	return parameters
}

func (w *writer) results(results *ast.FieldList) *contract.Node {
	if results == nil || len(results.List) == 0 {
		return nil
	}
	if len(results.List) == 1 && len(results.List[0].Names) == 0 && !results.Opening.IsValid() {
		return w.typeExpression(results.List[0].Type)
	}
	tuple := w.at("tuple-type", results)
	var elements []*contract.Node
	for _, field := range results.List {
		if len(field.Names) == 0 {
			elements = append(elements, hold(w.at("tuple-element", field), "type", w.typeExpression(field.Type)))
			continue
		}
		for _, name := range field.Names {
			element := w.between("tuple-element", name.Pos(), field.End())
			element.Own().Label = name.Name
			elements = append(elements, hold(element, "type", w.typeExpression(field.Type)))
		}
	}

	return holdAll(tuple, "elements", elements)
}

func (w *writer) typeParameters(list *ast.FieldList) []*contract.Node {
	if list == nil {
		return nil
	}
	var parameters []*contract.Node
	for _, field := range list.List {
		for _, name := range field.Names {
			parameter := w.between("type-parameter", name.Pos(), field.End())
			parameter.Own().Name = name.Name
			parameters = append(parameters, hold(parameter, "constraint", w.typeExpression(field.Type)))
		}
	}

	return parameters
}

// Statements ------------------------------------------------------------------------------------------------------

func (w *writer) block(block *ast.BlockStmt) *contract.Node {
	var statements []*contract.Node
	for _, statement := range block.List {
		statements = append(statements, w.statement(statement))
	}

	return holdAll(w.at("block", block), "statements", statements)
}

func (w *writer) statement(statement ast.Stmt) *contract.Node {
	switch held := statement.(type) {
	case *ast.BlockStmt:
		return w.block(held)
	case *ast.ExprStmt:
		if thrown := w.thrown(held.X); thrown != nil {
			return thrown
		}

		return hold(w.at("expression-statement", held), "expression", w.expression(held.X))
	case *ast.AssignStmt, *ast.IncDecStmt:
		return hold(w.at("expression-statement", held), "expression", w.simple(held))
	case *ast.DeclStmt:
		general, isGeneral := held.Decl.(*ast.GenDecl)
		if !isGeneral || len(general.Specs) == 0 {
			return w.unsupported(held, "declaration")
		}
		if spec, isValue := general.Specs[0].(*ast.ValueSpec); isValue && len(general.Specs) == 1 {
			return w.value(spec, held, general.Tok)
		}
		if spec, isType := general.Specs[0].(*ast.TypeSpec); isType && len(general.Specs) == 1 {
			return w.typeOf(spec, held)
		}

		return w.unsupported(held, "declaration")
	case *ast.ReturnStmt:
		node := w.at("return", held)
		var values []*contract.Node
		for _, result := range held.Results {
			values = append(values, w.expression(result))
		}

		return holdAll(node, "values", values)
	case *ast.IfStmt:
		node := w.at("if", held)
		if held.Init != nil {
			hold(node, "initializer", w.simple(held.Init))
		}
		hold(node, "condition", w.expression(held.Cond))
		hold(node, "then", w.block(held.Body))
		if held.Else != nil {
			hold(node, "else", w.statement(held.Else))
		}

		return node
	case *ast.ForStmt:
		node := w.at("loop", held)
		node.Own().Form = "for"
		if held.Init == nil && held.Post == nil {
			node.Own().Form = "while"
			if held.Cond == nil {
				node.Own().Form = "infinite"
			}
		}
		if held.Init != nil {
			holdAll(node, "initializer", []*contract.Node{w.simple(held.Init)})
		}
		if held.Cond != nil {
			hold(node, "condition", w.expression(held.Cond))
		}
		if held.Post != nil {
			holdAll(node, "steps", []*contract.Node{w.simple(held.Post)})
		}

		return hold(node, "body", w.block(held.Body))
	case *ast.RangeStmt:
		node := w.at("loop", held)
		node.Own().Form = "for-each"
		var bindings []*contract.Node
		for _, bound := range []ast.Expr{held.Key, held.Value} {
			if bound == nil {
				continue
			}
			if name, isName := bound.(*ast.Ident); isName && held.Tok == token.DEFINE {
				bindings = append(bindings, w.binding(name))
				continue
			}
			bindings = append(bindings, w.expression(bound))
		}
		holdAll(node, "bindings", bindings)
		hold(node, "iterable", w.expression(held.X))

		return hold(node, "body", w.block(held.Body))
	case *ast.SwitchStmt:
		node := w.at("dispatch", held)
		node.Own().Form = "switch"
		if held.Tag != nil {
			hold(node, "subject", w.expression(held.Tag))
		}

		return holdAll(node, "arms", w.arms(held.Body, func(written ast.Expr) *contract.Node {
			pattern := w.at("pattern", written)
			pattern.Own().Form = "value"

			return hold(pattern, "value", w.expression(written))
		}))
	case *ast.TypeSwitchStmt:
		node := w.at("dispatch", held)
		node.Own().Form = "type-switch"
		test := w.at("type-test", held.Assign)
		test.Own().Form = "type-switch"
		switch guard := held.Assign.(type) {
		case *ast.AssignStmt:
			if asserted, isAssert := guard.Rhs[0].(*ast.TypeAssertExpr); isAssert {
				hold(test, "subject", w.expression(asserted.X))
			}
			if name, isName := guard.Lhs[0].(*ast.Ident); isName {
				hold(test, "binding", w.binding(name))
			}
		case *ast.ExprStmt:
			if asserted, isAssert := guard.X.(*ast.TypeAssertExpr); isAssert {
				hold(test, "subject", w.expression(asserted.X))
			}
		}
		hold(node, "subject", test)

		return holdAll(node, "arms", w.arms(held.Body, func(written ast.Expr) *contract.Node {
			pattern := w.at("pattern", written)
			pattern.Own().Form = "type"

			return hold(pattern, "constrainedType", w.typeExpression(written))
		}))
	case *ast.SelectStmt:
		node := w.at("select", held)
		var arms []*contract.Node
		for _, clause := range held.Body.List {
			communication := clause.(*ast.CommClause)
			arm := w.at("select-arm", communication)
			if communication.Comm != nil {
				hold(arm, "communication", w.statement(communication.Comm))
			}
			arms = append(arms, hold(arm, "body", w.clauseBody(communication.Colon, communication.Body, communication.End())))
		}

		return holdAll(node, "arms", arms)
	case *ast.BranchStmt:
		switch held.Tok {
		case token.BREAK, token.CONTINUE:
			node := w.at(held.Tok.String(), held)
			if held.Label != nil {
				label := w.at("reference", held.Label)
				label.Own().Name, label.Own().Form = held.Label.Name, "name"
				hold(node, "label", label)
			}

			return node
		case token.GOTO:
			destination := w.at("reference", held.Label)
			destination.Own().Name, destination.Own().Form = held.Label.Name, "name"

			return hold(w.at("goto", held), "destination", destination)
		}

		return w.unsupported(held, "fallthrough")
	case *ast.LabeledStmt:
		node := hold(w.at("label", held), "binding", w.binding(held.Label))

		return hold(node, "statement", w.statement(held.Stmt))
	case *ast.DeferStmt:
		return hold(w.at("defer", held), "call", w.expression(held.Call))
	case *ast.GoStmt:
		return w.unsupported(held, "go")
	case *ast.SendStmt:
		return w.unsupported(held, "send")
	case *ast.EmptyStmt:
		return w.at("no-op", held)
	case *ast.BadStmt:
		bad := w.at("unsupported", held)
		bad.Own().Construct, bad.Own().Incomplete = "statement", true

		return bad
	}

	return w.unsupported(statement, "statement")
}

// simple is a statement written where Go takes one inside a header (an if's or a for's initializer, a for's post
// statement) or as a statement of its own: an assignment, an increment, or an expression.
func (w *writer) simple(statement ast.Stmt) *contract.Node {
	switch held := statement.(type) {
	case *ast.AssignStmt:
		node := w.at("assignment", held)
		var targets, values []*contract.Node
		for _, target := range held.Lhs {
			name, isName := target.(*ast.Ident)
			if isName && held.Tok == token.DEFINE && (w.facts == nil || w.facts.Defs[name] != nil || name.Name == "_") {
				targets = append(targets, w.binding(name))
				continue
			}
			targets = append(targets, w.expression(target))
		}
		for _, value := range held.Rhs {
			values = append(values, w.expression(value))
		}
		switch {
		case held.Tok == token.DEFINE:
			node.Own().Mode = "define"
		case held.Tok == token.ASSIGN:
			node.Own().Mode = "assign"
		default:
			node.Own().Mode, node.Own().Operator = "compound", compounds[held.Tok]
		}
		holdAll(node, "targets", targets)

		return holdAll(node, "values", values)
	case *ast.IncDecStmt:
		node := w.at("assignment", held)
		node.Own().Mode = "increment"
		if held.Tok == token.DEC {
			node.Own().Mode = "decrement"
		}

		return holdAll(node, "targets", []*contract.Node{w.expression(held.X)})
	case *ast.ExprStmt:
		return w.expression(held.X)
	}

	return w.statement(statement)
}

func (w *writer) arms(body *ast.BlockStmt, pattern func(ast.Expr) *contract.Node) []*contract.Node {
	var arms []*contract.Node
	for at, clause := range body.List {
		written := clause.(*ast.CaseClause)
		arm := w.at("arm", written)
		var patterns []*contract.Node
		for _, each := range written.List {
			patterns = append(patterns, pattern(each))
		}
		arm.Own().Default = written.List == nil
		if last := len(written.Body) - 1; last >= 0 && at < len(body.List)-1 {
			if branch, isBranch := written.Body[last].(*ast.BranchStmt); isBranch && branch.Tok == token.FALLTHROUGH {
				arm.Own().FallsThrough = true
			}
		}
		holdAll(arm, "patterns", patterns)
		arms = append(arms, hold(arm, "body", w.clauseBody(written.Colon, written.Body, written.End())))
	}

	return arms
}

// clauseBody is a case's statements as a block: from the first statement to the last, or empty after its colon.
func (w *writer) clauseBody(colon token.Pos, statements []ast.Stmt, end token.Pos) *contract.Node {
	written := slices.DeleteFunc(slices.Clone(statements), func(statement ast.Stmt) bool {
		branch, isBranch := statement.(*ast.BranchStmt)

		return isBranch && branch.Tok == token.FALLTHROUGH
	})
	if len(written) == 0 {
		return w.between("block", colon+1, colon+1)
	}
	block := w.between("block", written[0].Pos(), written[len(written)-1].End())
	var held []*contract.Node
	for _, statement := range written {
		held = append(held, w.statement(statement))
	}

	return holdAll(block, "statements", held)
}

func (w *writer) unsupported(held ast.Node, construct string) *contract.Node {
	node := w.at("unsupported", held)
	node.Own().Construct = construct
	var parts []*contract.Node
	ast.Inspect(held, func(child ast.Node) bool {
		if child == held {
			return true
		}
		if expression, isExpression := child.(ast.Expr); isExpression {
			parts = append(parts, w.expression(expression))

			return false
		}

		return true
	})

	return holdAll(node, "parts", parts)
}

// thrown is `panic(x)` where the compiler resolves `panic` to the builtin: a throw; a shadowed panic is a call.
func (w *writer) thrown(expression ast.Expr) *contract.Node {
	call, isCall := expression.(*ast.CallExpr)
	if !isCall || w.facts == nil || len(call.Args) != 1 {
		return nil
	}
	name, isName := call.Fun.(*ast.Ident)
	if !isName {
		return nil
	}
	if builtin, isBuiltin := w.facts.Uses[name].(*types.Builtin); !isBuiltin || builtin.Name() != "panic" {
		return nil
	}

	return hold(w.at("throw", call), "value", w.expression(call.Args[0]))
}

// Expressions -----------------------------------------------------------------------------------------------------

func (w *writer) expression(expression ast.Expr) *contract.Node {
	node := w.translated(expression)
	if w.facts == nil || node.Kind == "unsupported" {
		return node
	}
	held, found := w.facts.Types[expression]
	if !found || held.IsType() {
		return node
	}
	if held.Type != nil {
		node.Resolved = w.resolved(held.Type)
	}
	if held.Value != nil && node.Kind != "literal" {
		node.Own().ConstantValue = &contract.ConstantValue{Type: types.TypeString(held.Type, qualify), Value: quoted(constantOf(held.Value))}
	}

	return node
}

func (w *writer) resolved(held types.Type) *contract.Type {
	if known, seen := w.types[held]; seen {
		return known
	}
	described := typeOf(held, "compiler")
	w.types[held] = described

	return described
}

func constantOf(value constant.Value) any {
	switch value.Kind() {
	case constant.Bool:
		return constant.BoolVal(value)
	case constant.String:
		return constant.StringVal(value)
	}

	return value.ExactString()
}

func (w *writer) translated(expression ast.Expr) *contract.Node {
	switch held := expression.(type) {
	case *ast.ParenExpr:
		return w.expression(held.X)
	case *ast.Ident:
		return w.name(held)
	case *ast.BasicLit:
		return w.literal(held)
	case *ast.CompositeLit:
		return w.composite(held)
	case *ast.FuncLit:
		return w.signature(w.at("function", held), nil, "closure", nil, held.Type, held.Body)
	case *ast.SelectorExpr:
		node := w.at("access", held)
		node.Own().Form = "instance"
		if w.isPackage(held.X) {
			node.Own().Form = "static"
		}
		hold(node, "subject", w.expression(held.X))

		return hold(node, "member", w.reference(held.Sel))
	case *ast.IndexExpr:
		if w.isType(held) {
			return w.typeExpression(held)
		}
		node := hold(w.at("index", held), "subject", w.expression(held.X))

		return hold(node, "key", w.expression(held.Index))
	case *ast.TypeAssertExpr:
		node := w.at("cast", held)
		node.Own().Form = "checked"
		hold(node, "operand", w.expression(held.X))
		if held.Type != nil {
			hold(node, "targetType", w.typeExpression(held.Type))
		}

		return node
	case *ast.CallExpr:
		return w.call(held)
	case *ast.StarExpr:
		if w.isType(held) {
			return w.typeExpression(held)
		}
		node := w.at("unary", held)
		node.Own().Operator = "dereference"

		return hold(node, "operand", w.expression(held.X))
	case *ast.UnaryExpr:
		node := w.at("unary", held)
		node.Own().Operator = unaries[held.Op]

		return hold(node, "operand", w.expression(held.X))
	case *ast.BinaryExpr:
		return w.binary(held)
	case *ast.ArrayType, *ast.MapType, *ast.ChanType, *ast.FuncType, *ast.StructType, *ast.InterfaceType:
		return w.typeExpression(held)
	case *ast.BadExpr:
		bad := w.at("unsupported", held)
		bad.Own().Construct, bad.Own().Incomplete = "expression", true

		return bad
	}

	return w.unsupported(expression, "expression")
}

// name is a name read: Go's `nil`, `true` and `false` are literals when the compiler resolves them to the universe.
func (w *writer) name(name *ast.Ident) *contract.Node {
	if w.facts != nil {
		switch used := w.facts.Uses[name].(type) {
		case *types.Nil:
			literal := w.at("literal", name)
			literal.Own().LiteralKind = "null"

			return literal
		case *types.Const:
			if used.Parent() == types.Universe && (used.Name() == "true" || used.Name() == "false") {
				literal := w.at("literal", name)
				literal.Own().LiteralKind, literal.Own().Value = "boolean", quoted(used.Name() == "true")

				return literal
			}
		}
	}

	return w.reference(name)
}

func (w *writer) literal(written *ast.BasicLit) *contract.Node {
	node := w.at("literal", written)
	switch written.Kind {
	case token.INT:
		node.Own().LiteralKind, node.Own().Value = "integer", quoted(decimal(written.Value))
	case token.FLOAT, token.IMAG:
		node.Own().LiteralKind, node.Own().Value = "float", quoted(written.Value)
	case token.CHAR:
		text, _ := strconv.Unquote(written.Value)
		node.Own().LiteralKind, node.Own().Value = "character", quoted(text)
	case token.STRING:
		text, _ := strconv.Unquote(written.Value)
		node.Own().LiteralKind, node.Own().Value = "string", quoted(text)
	}

	return node
}

// composite is a list or a map literal as a collection, and a struct literal as a construction of its type with its
// fields as an object initializer, as C#'s `new Account { Total = 0 }` is.
func (w *writer) composite(written *ast.CompositeLit) *contract.Node {
	form := w.compositeForm(written)
	if form != "object" || written.Type == nil {
		return w.entries(w.at("collection", written), form, written)
	}
	construction := hold(w.at("construction", written), "type", w.typeExpression(written.Type))

	return hold(construction, "initializer", w.entries(w.between("collection", written.Lbrace, written.Rbrace+1), form, written))
}

// compositeForm is what a composite literal builds: the checker's type when it ran, else the type as written.
func (w *writer) compositeForm(written *ast.CompositeLit) string {
	if w.facts != nil {
		if built := w.facts.TypeOf(written); built != nil {
			switch built.Underlying().(type) {
			case *types.Map:
				return "map"
			case *types.Slice, *types.Array:
				return "list"
			case *types.Struct:
				return "object"
			}
		}
	}
	switch {
	case isMap(written.Type):
		return "map"
	case written.Type != nil && !isList(written.Type):
		return "object"
	}

	return "list"
}

func (w *writer) entries(node *contract.Node, form string, written *ast.CompositeLit) *contract.Node {
	node.Own().Form = form
	var entries []*contract.Node
	for _, element := range written.Elts {
		if pair, isPair := element.(*ast.KeyValueExpr); isPair {
			entry := w.at("entry", pair)
			key := w.expression(pair.Key)
			if name, isName := pair.Key.(*ast.Ident); isName && node.Own().Form == "object" {
				key = w.reference(name)
			}
			hold(entry, "key", key)
			entries = append(entries, hold(entry, "value", w.expression(pair.Value)))
			continue
		}
		entries = append(entries, hold(w.at("entry", element), "value", w.expression(element)))
	}

	return holdAll(node, "entries", entries)
}

func isMap(written ast.Expr) bool {
	_, isMap := written.(*ast.MapType)

	return isMap
}

func isList(written ast.Expr) bool {
	_, isArray := written.(*ast.ArrayType)

	return isArray
}

func (w *writer) call(call *ast.CallExpr) *contract.Node {
	if w.isType(call.Fun) && len(call.Args) == 1 {
		cast := w.at("cast", call)
		cast.Own().Form = "checked"
		hold(cast, "operand", w.expression(call.Args[0]))

		return hold(cast, "targetType", w.typeExpression(call.Fun))
	}
	if thrown := w.thrown(call); thrown != nil {
		return thrown
	}
	node := hold(w.at("call", call), "callee", w.expression(call.Fun))
	if w.facts != nil {
		node.Own().Target = w.target(call)
	}
	var arguments []*contract.Node
	for at, written := range call.Args {
		argument := w.at("argument", written)
		if at == len(call.Args)-1 && call.Ellipsis.IsValid() {
			argument = w.between("argument", written.Pos(), call.Ellipsis+3)
			argument.Own().Spread = "positional"
		}
		arguments = append(arguments, hold(argument, "value", w.expression(written)))
	}

	return holdAll(node, "arguments", arguments)
}

func (w *writer) binary(binary *ast.BinaryExpr) *contract.Node {
	if binary.Op == token.LAND || binary.Op == token.LOR {
		node := w.at("logical", binary)
		node.Own().Operator = "and"
		if binary.Op == token.LOR {
			node.Own().Operator = "or"
		}
		hold(node, "left", w.expression(binary.X))

		return hold(node, "right", w.expression(binary.Y))
	}
	if comparison, compares := comparisons[binary.Op]; compares {
		node := w.at("comparison", binary)
		node.Own().Operator, node.Own().Operators = comparison, []string{comparison}
		left, right := w.expression(binary.X), w.expression(binary.Y)
		holdAll(node, "operands", []*contract.Node{left, right})
		w.link(node, "left", []*contract.Node{left}, false)
		w.link(node, "right", []*contract.Node{right}, false)

		return node
	}
	node := w.at("binary", binary)
	node.Own().Operator = binaries[binary.Op]
	hold(node, "left", w.expression(binary.X))

	return hold(node, "right", w.expression(binary.Y))
}

func (w *writer) isPackage(subject ast.Expr) bool {
	name, isName := subject.(*ast.Ident)
	if !isName {
		return false
	}
	if w.facts != nil {
		_, isPackage := w.facts.Uses[name].(*types.PkgName)

		return isPackage
	}

	return w.imported[name.Name]
}

func (w *writer) isType(expression ast.Expr) bool {
	if w.facts == nil {
		return false
	}
	held, found := w.facts.Types[expression]

	return found && held.IsType()
}

// Types as written ------------------------------------------------------------------------------------------------

func (w *writer) typeExpression(written ast.Expr) *contract.Node {
	node := w.at("type-expression", written)
	switch held := written.(type) {
	case *ast.ParenExpr:
		return w.typeExpression(held.X)
	case *ast.Ident:
		node.Own().Form = "named"
		if w.facts != nil {
			node.Own().Refers = w.refers(held)
		}
	case *ast.SelectorExpr:
		node.Own().Form = "named"
		if w.facts != nil {
			node.Own().Refers = w.refers(held.Sel)
		}
	case *ast.StarExpr:
		node.Own().Form = "pointer"
		hold(node, "element", w.typeExpression(held.X))
	case *ast.ArrayType:
		node.Own().Form = "array"
		hold(node, "element", w.typeExpression(held.Elt))
	case *ast.Ellipsis:
		node.Own().Form = "array"
		hold(node, "element", w.typeExpression(held.Elt))
	case *ast.MapType:
		node.Own().Form = "map"
		holdAll(node, "typeArguments", []*contract.Node{w.typeExpression(held.Key), w.typeExpression(held.Value)})
	case *ast.FuncType:
		node.Own().Form = "function"
		if held.Params != nil {
			var parameters []*contract.Node
			for _, field := range held.Params.List {
				parameters = append(parameters, w.parameters(field)...)
			}
			holdAll(node, "parameters", parameters)
		}
		hold(node, "returnType", w.results(held.Results))
	case *ast.IndexExpr:
		node = w.typeExpression(held.X)
		node.Span = w.at("type-expression", written).Span
		holdAll(node, "typeArguments", []*contract.Node{w.typeExpression(held.Index)})
	case *ast.IndexListExpr:
		node = w.typeExpression(held.X)
		node.Span = w.at("type-expression", written).Span
		var arguments []*contract.Node
		for _, argument := range held.Indices {
			arguments = append(arguments, w.typeExpression(argument))
		}
		holdAll(node, "typeArguments", arguments)
	default:
		node.Own().Form = "opaque"
	}

	return node
}

// Numbering -------------------------------------------------------------------------------------------------------

// numbered numbers the tree in pre-order, each node's children in source order, sets every pending link and
// returns the nodes by number.
func (w *writer) numbered(root *contract.Node) []*contract.Node {
	var order []*contract.Node
	pending := []*contract.Node{root}
	for len(pending) > 0 {
		node := pending[len(pending)-1]
		pending = pending[:len(pending)-1]
		node.ID = len(order)
		order = append(order, node)
		children := childrenOf(node)
		for at := len(children) - 1; at >= 0; at-- {
			pending = append(pending, children[at])
		}
	}
	for _, link := range w.links {
		ids := make([]int, 0, len(link.to))
		for _, target := range link.to {
			ids = append(ids, target.ID)
		}
		link.from.Links = append(link.from.Links, contract.Link{Name: link.name, IDs: ids, List: link.list})
	}

	return order
}

func childrenOf(node *contract.Node) []*contract.Node {
	var children []*contract.Node
	for _, part := range node.Parts {
		children = append(children, part.Nodes...)
	}
	slices.SortStableFunc(children, func(a, b *contract.Node) int {
		if a.Span.Start != b.Span.Start {
			return a.Span.Start - b.Span.Start
		}

		return b.Span.End - a.Span.End
	})

	return children
}
