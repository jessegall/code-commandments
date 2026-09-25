package typescript

import (
	"slices"
	"strings"
)

// A script read as the PHP tool's own parser reads it: its imports, and the variables, functions, classes, interfaces
// and type aliases at its top, as tokens reveal them. What an extracted component declares is built from these
// readings, so they are taken the way the PHP tool takes them rather than off the compiler's tree.

// Import is an import statement: what it binds, local name to imported name, and where from.
type Import struct {
	Bindings map[string]string
	Names    []string
	Source   string
	HasFrom  bool
	TypeOnly bool
	Raw      string
}

// Variable is a `const`/`let`/`var` declaration.
type Variable struct {
	Keyword        string
	Pattern        string
	Names          []string
	ObjectPattern  bool
	NamePattern    bool
	TypeAnnotation TypeNode
	InitRaw        string
	HasInit        bool
	InitCall       *Call
	InitParams     []Param
	HasInitParams  bool
	InitReturnType TypeNode
}

// Render is the declaration as written again: `const name: T = init;`.
func (v Variable) Render() string {
	typed, init := "", ""
	if v.TypeAnnotation != nil {
		typed = ": " + v.TypeAnnotation.Render()
	}
	if v.HasInit {
		init = " = " + v.InitRaw
	}

	return v.Keyword + " " + v.Pattern + typed + init + ";"
}

// Function is a function declaration.
type Function struct {
	Name         string
	Params       []Param
	ReturnType   TypeNode
	ReturnObject *Fields
	BodySource   string
}

// Signature is the function's type: its parameters and its return, void when it declares none.
func (f Function) Signature() FunctionType {
	var returns TypeNode = KeywordType{Name: "void"}
	if f.ReturnType != nil {
		returns = f.ReturnType
	}

	return FunctionType{Params: f.Params, Returns: returns}
}

// Call is a call at the top of a script, or the one a variable is initialised with.
type Call struct {
	Callee        string
	TypeArguments []TypeNode
	Arguments     []string
}

// FirstArgumentStartsWith says whether the call's first argument is written starting with prefix.
func (c Call) FirstArgumentStartsWith(prefix string) bool {
	return len(c.Arguments) > 0 && strings.HasPrefix(c.Arguments[0], prefix)
}

// TypeDeclaration is an interface or a type alias.
type TypeDeclaration struct {
	Name      string
	Header    string
	Interface bool
	Members   []Member
	Type      TypeNode
}

// Render is the declaration as the PHP tool writes it again: an interface a member a line, an alias on one line.
func (d TypeDeclaration) Render() string {
	if !d.Interface {
		return "type " + d.Name + d.Header + " = " + d.Type.Render() + ";"
	}
	lines := make([]string, 0, len(d.Members))
	for _, member := range d.Members {
		lines = append(lines, "    "+member.Render()+";")
	}

	return "interface " + d.Name + d.Header + " {\n" + strings.Join(lines, "\n") + "\n}"
}

// References is every name the declaration's types reference.
func (d TypeDeclaration) References() []string {
	if !d.Interface {
		return d.Type.References()
	}

	return ObjectType{Members: d.Members}.References()
}

// Fields is the declaration's members by name, each its type as printed.
func (d TypeDeclaration) Fields() Fields {
	if d.Interface {
		return ObjectType{Members: d.Members}.Fields()
	}
	if object, ok := d.Type.(ObjectType); ok {
		return object.Fields()
	}

	return Fields{}
}

// statement is one top-level statement a script's reading keeps.
type statement struct {
	variable    *Variable
	function    *Function
	class       string
	declaration *TypeDeclaration
	call        *Call
}

// Module is a script as the PHP tool's parser reads it.
type Module struct {
	Imports []Import
	body    []statement
}

// ParseModule reads a script.
func ParseModule(source string) Module {
	parser := &typeParser{source: source, tokens: lex(source)}
	var module Module
	for !parser.eof() {
		before := parser.pos
		imported, read := parser.parseStatement()
		switch {
		case imported != nil:
			module.Imports = append(module.Imports, *imported)
		case read != nil:
			module.body = append(module.body, *read)
		}
		if parser.pos == before {
			parser.advance()
		}
	}

	return module
}

// Variable is the declaration of a variable binding the name.
func (m Module) Variable(name string) (Variable, bool) {
	for _, read := range m.body {
		if read.variable != nil && slices.Contains(read.variable.Names, name) {
			return *read.variable, true
		}
	}

	return Variable{}, false
}

// Function is the function declared under the name.
func (m Module) Function(name string) (Function, bool) {
	for _, read := range m.body {
		if read.function != nil && read.function.Name == name {
			return *read.function, true
		}
	}

	return Function{}, false
}

// TypeDeclaration is the interface declared under the name, else the type alias.
func (m Module) TypeDeclaration(name string) (TypeDeclaration, bool) {
	for _, wantInterface := range []bool{true, false} {
		for _, read := range m.body {
			if read.declaration != nil && read.declaration.Interface == wantInterface && read.declaration.Name == name {
				return *read.declaration, true
			}
		}
	}

	return TypeDeclaration{}, false
}

// Call is the first call to the callee at the top of the script, a variable's initialiser included.
func (m Module) Call(callee string) (Call, bool) {
	for _, read := range m.body {
		switch {
		case read.call != nil && read.call.Callee == callee:
			return *read.call, true
		case read.variable != nil && read.variable.InitCall != nil && read.variable.InitCall.Callee == callee:
			return *read.variable.InitCall, true
		}
	}

	return Call{}, false
}

// LocalNames is every name the script's top declares, in order.
func (m Module) LocalNames() []string {
	var names []string
	for _, read := range m.body {
		switch {
		case read.variable != nil:
			names = append(names, read.variable.Names...)
		case read.function != nil:
			names = append(names, read.function.Name)
		case read.class != "":
			names = append(names, read.class)
		}
	}

	return names
}

// VariableNamedFrom is the name of the first plain `const name = …` whose initialiser call the test accepts.
func (m Module) VariableNamedFrom(test func(Call) bool) (string, bool) {
	for _, read := range m.body {
		if variable := read.variable; variable != nil && variable.NamePattern && variable.InitCall != nil && test(*variable.InitCall) {
			return variable.Pattern, true
		}
	}

	return "", false
}

// LocalTypes is each declaration the names reach within the script, the ones they reference followed in turn,
// printed, keyed by name in the order they were reached.
func (m Module) LocalTypes(names []string) Fields {
	var rendered Fields
	seen := map[string]bool{}
	var queue []string
	for _, name := range names {
		if !slices.Contains(queue, name) {
			queue = append(queue, name)
		}
	}
	for len(queue) > 0 {
		name := queue[0]
		queue = queue[1:]
		if seen[name] {
			continue
		}
		seen[name] = true
		declaration, declared := m.TypeDeclaration(name)
		if !declared {
			continue
		}
		rendered.Set(name, declaration.Render())
		queue = append(queue, declaration.References()...)
	}

	return rendered
}

func (p *typeParser) parseStatement() (imported *Import, read *statement) {
	defer func() {
		if failure := recover(); failure != nil {
			if _, isUnparsed := failure.(unparsed); !isUnparsed {
				panic(failure)
			}
			p.skipStatement()
			imported, read = nil, nil
		}
	}()

	return p.parseModelledStatement()
}

func (p *typeParser) parseModelledStatement() (*Import, *statement) {
	switch {
	case p.atID("import") && !p.at(1).isPunct(".") && !p.at(1).isPunct("("):
		imported := p.parseImport()

		return &imported, nil
	case p.atID("export"):
		p.advance()

		return p.parseStatement()
	case p.atID("interface"):
		return nil, &statement{declaration: p.parseInterface()}
	case p.atID("type") && p.at(1).isIdentifier(""):
		return nil, &statement{declaration: p.parseTypeAlias()}
	case p.atPunct(";"):
		p.consumeToStatementEnd(nil)
	case p.atPunct("{"):
		p.skipBlock()
	case p.atID("if"):
		p.skipIf()
	case p.atID("switch"):
		p.advance()
		p.skipGroup()
		p.skipBlock()
	case p.atID("try"):
		p.skipTry()
	case p.atID("return") || p.atID("throw"):
		p.consumeToStatementEnd(nil)
	case p.atID("for") || p.atID("while"):
		p.advance()
		p.skipGroup()
		p.skipBody()
	case p.atID("do"):
		p.advance()
		p.skipBody()
		if p.atID("while") {
			p.advance()
			p.skipGroup()
			p.advanceIfPunct(";")
		}
	case p.atID("break") || p.atID("continue"):
		p.advance()
		if p.peek().isIdentifier("") {
			p.advance()
		}
		p.advanceIfPunct(";")
	case p.peek().isIdentifier("") && !p.atID("case") && !p.atID("default") && p.at(1).isPunct(":"):
		p.advance()
		p.advance()

		return p.parseStatement()
	case p.atID("const") || p.atID("let") || p.atID("var"):
		variable := p.parseVariable()

		return nil, &statement{variable: &variable}
	case p.atID("class") || p.atID("abstract") && p.at(1).isIdentifier("class"):
		return nil, &statement{class: p.parseClass()}
	case p.atID("function") || p.atID("async") && p.at(1).isIdentifier("function"):
		function := p.parseFunction()

		return nil, &statement{function: &function}
	case p.peek().isIdentifier("") && (p.at(1).isPunct("(") || p.at(1).isPunct("<")):
		call, ok := speculate(p, p.parseCall)
		if !ok {
			p.consumeToStatementEnd(nil)

			return nil, nil
		}
		p.consumeToStatementEnd(p.lastConsumed())

		return nil, &statement{call: &call}
	default:
		p.consumeToStatementEnd(nil)
	}

	return nil, nil
}

func (p *typeParser) parseImport() Import {
	start := p.peek().start
	imported := Import{Bindings: map[string]string{}}
	bind := func(local, name string) {
		if _, seen := imported.Bindings[local]; !seen {
			imported.Names = append(imported.Names, local)
		}
		imported.Bindings[local] = name
	}
	p.advance()
	if p.atID("type") {
		imported.TypeOnly = true
		p.advance()
	}
	if p.peek().isIdentifier("") && p.at(1).isPunct("=") {
		local := p.advance().value
		p.advance()
		bind(local, p.qualifiedName())
	} else {
		p.parseImportBindings(bind)
		if p.atID("from") {
			p.advance()
			if p.peek().is(stringToken, "") {
				value := p.advance().value
				imported.Source, imported.HasFrom = value[1:len(value)-1], true
			}
		} else if p.peek().is(stringToken, "") {
			value := p.advance().value
			imported.Source, imported.HasFrom = value[1:len(value)-1], true
		}
	}
	end := p.consumeToStatementEnd(nil)
	imported.Raw = strings.Trim(p.source[start:end], " \t\n\r\x00\x0b")

	return imported
}

func (p *typeParser) parseImportBindings(bind func(local, name string)) {
	if p.peek().isIdentifier("") && !p.atPunct("{") && !p.atPunct("*") {
		bind(p.advance().value, "default")
		if p.atPunct(",") {
			p.advance()
		}
	}
	if p.atPunct("*") {
		p.advance()
		if p.atID("as") {
			p.advance()
		}
		bind(p.advance().value, "*")
	}
	if !p.atPunct("{") {
		return
	}
	p.advance()
	for p.inside("}") {
		if p.atID("type") {
			p.advance()
		}
		name := p.advance().value
		local := name
		if p.atID("as") {
			p.advance()
			local = p.advance().value
		}
		bind(local, name)
		if p.atPunct(",") {
			p.advance()
		}
	}
	p.advanceIfPunct("}")
}

func (p *typeParser) parseInterface() *TypeDeclaration {
	p.advance()
	name := p.advance().value
	header := p.consumeUntilPunct("{")

	return &TypeDeclaration{Name: name, Header: header, Interface: true, Members: p.parseLooseTypeMembers()}
}

// parseLooseTypeMembers reads an interface's members, passing over a member it does not model.
func (p *typeParser) parseLooseTypeMembers() []Member {
	p.expectPunct("{")
	var members []Member
	for p.inside("}") {
		if named := p.peek().isIdentifier("") || p.peek().is(stringToken, "") || p.atID("readonly"); !named {
			p.consumeMemberVerbatim()
			p.advanceIfPunct(";")
			p.advanceIfPunct(",")

			continue
		}
		members = append(members, p.parseLooseTypeMember())
		p.advanceIfPunct(";")
		p.advanceIfPunct(",")
	}
	p.expectPunct("}")

	return members
}

func (p *typeParser) parseLooseTypeMember() Member {
	if p.atReadonlyModifier() {
		p.advance()
	}
	name := p.advance().value
	optional := p.advanceIfPunct("?")
	if p.atPunct("(") {
		params := p.parseParams()
		var returns TypeNode = KeywordType{Name: "void"}
		if p.advanceIfPunct(":") {
			returns = p.parseType()
		}

		return Member{Name: name, Optional: optional, Params: params, Returns: returns}
	}
	if p.advanceIfPunct(":") {
		return Member{Name: name, Optional: optional, Property: p.parseType()}
	}
	panic(unparsed{})
}

func (p *typeParser) consumeMemberVerbatim() {
	depth := 0
	for !p.eof() {
		token := p.peek()
		if token.isPunct("=") && p.at(1).isPunct(">") {
			p.advance()
			p.advance()

			continue
		}
		if depth == 0 && (token.isPunct(";") || token.isPunct(",") || token.isPunct("}")) {
			return
		}
		if token.isTypeOpener() {
			depth++
		} else if token.isTypeCloser() {
			depth--
		}
		p.advance()
	}
}

func (p *typeParser) parseTypeAlias() *TypeDeclaration {
	p.advance()
	name := p.advance().value
	header := p.consumeUntilPunct("=")
	p.advanceIfPunct("=")
	typed := p.parseType()
	p.advanceIfPunct(";")

	return &TypeDeclaration{Name: name, Header: header, Type: typed}
}

func (p *typeParser) parseVariable() Variable {
	variable := Variable{Keyword: p.advance().value}
	variable.Pattern, variable.Names, variable.ObjectPattern = p.parseBindingPattern()
	variable.NamePattern = !variable.ObjectPattern && !strings.HasPrefix(variable.Pattern, "[")
	if p.atPunct(":") {
		p.advance()
		variable.TypeAnnotation = p.parseType()
	}
	if !p.atPunct("=") {
		p.advanceIfPunct(";")

		return variable
	}
	p.advance()
	if p.atID("await") {
		p.advance()
	}
	initStart := p.peek().start
	if p.atID("async") && p.at(1).isPunct("(") {
		p.advance()
	}
	switch {
	case p.peek().isIdentifier("") && (p.at(1).isPunct("(") || p.at(1).isPunct("<")):
		if call, ok := speculate(p, p.parseCall); ok {
			variable.InitCall = &call
		}
	case p.atPunct("("):
		if signature, ok := speculate(p, func() Variable {
			params := p.parseParams()
			var returns TypeNode
			if p.advanceIfPunct(":") {
				returns = p.parseType()
			}
			if !p.atPunct("=") || !p.at(1).isPunct(">") {
				panic(unparsed{})
			}

			return Variable{InitParams: params, HasInitParams: true, InitReturnType: returns}
		}); ok {
			variable.InitParams, variable.HasInitParams, variable.InitReturnType = signature.InitParams, true, signature.InitReturnType
		}
	}
	initEnd := p.consumeToStatementEnd(p.lastConsumed())
	raw := strings.Trim(p.source[initStart:max(initStart, initEnd)], " \t\n\r\x00\x0b")
	variable.InitRaw, variable.HasInit = strings.TrimRight(raw, ";"), true

	return variable
}

// parseBindingPattern reads what a declaration binds: a name, `{ … }` or `[ … ]`, printed, and the names it binds.
func (p *typeParser) parseBindingPattern() (string, []string, bool) {
	if !p.atPunct("{") && !p.atPunct("[") {
		name := p.advance().value

		return name, []string{name}, false
	}
	object := p.atPunct("{")
	printed, names := p.readPattern()

	return printed, names, object
}

func (p *typeParser) parseFunction() Function {
	if p.atID("async") {
		p.advance()
	}
	p.advance()
	p.advanceIfPunct("*")
	function := Function{Name: p.advance().value}
	p.consumeUntilPunct("(")
	function.Params = p.parseParams()
	if p.atPunct(":") {
		p.advance()
		function.ReturnType = p.parseType()
	}
	bodyStart := p.peek().start + 1
	function.ReturnObject = p.skipBodyCapturingReturn()
	bodyEnd := bodyStart
	if p.pos > 0 {
		bodyEnd = p.tokens[p.pos-1].start
	}
	if bodyEnd > bodyStart {
		function.BodySource = p.source[bodyStart:bodyEnd]
	}

	return function
}

func (p *typeParser) skipBodyCapturingReturn() *Fields {
	if !p.atPunct("{") {
		return nil
	}
	p.advance()
	depth := 1
	var returned *Fields
	for !p.eof() && depth > 0 {
		if depth == 1 && p.atID("return") && p.at(1).isPunct("{") {
			p.advance()
			shape := p.parseObjectShape()
			returned = &shape

			continue
		}
		token := p.advance()
		if token.isPunct("{") {
			depth++
		} else if token.isPunct("}") {
			depth--
		}
	}

	return returned
}

// parseObjectShape reads a returned object literal: each key and the local it returns, empty when the value is
// computed.
func (p *typeParser) parseObjectShape() Fields {
	p.advance()
	var shape Fields
	for p.inside("}") {
		before := p.pos
		switch {
		case p.atPunct(".") && p.at(1).isPunct(".") && p.at(2).isPunct("."):
			p.advanceIfThreeDots()
			p.consumeExpression(",", "}")
		case p.peek().isIdentifier("") || p.peek().is(stringToken, ""):
			key := p.advance().value
			switch {
			case !p.advanceIfPunct(":"):
				shape.Set(key, key)
			case p.peek().isIdentifier("") && (p.at(1).isPunct(",") || p.at(1).isPunct("}")):
				shape.Set(key, p.advance().value)
			default:
				shape.Set(key, "")
				p.consumeExpression(",", "}")
			}
		default:
			p.consumeExpression(",", "}")
		}
		p.advanceIfPunct(",")
		if p.pos == before {
			p.advance()
		}
	}
	p.advanceIfPunct("}")

	return shape
}

func (p *typeParser) parseClass() string {
	if p.atID("abstract") {
		p.advance()
	}
	p.advance()
	name := ""
	if p.peek().isIdentifier("") {
		name = p.advance().value
	}
	p.consumeUntilPunct("{")
	p.skipBlock()

	return name
}

func (p *typeParser) parseCall() Call {
	call := Call{Callee: p.qualifiedName()}
	if p.atPunct("<") {
		call.TypeArguments = p.parseTypeArguments()
	}
	if p.atPunct("(") {
		p.advance()
		for p.inside(")") {
			start := p.peek().start
			end := p.consumeExpression(",", ")")
			call.Arguments = append(call.Arguments, strings.Trim(p.source[start:max(start, end)], " \t\n\r\x00\x0b"))
			if p.atPunct(",") {
				p.advance()
			}
		}
		p.advanceIfPunct(")")
	}

	return call
}

func (p *typeParser) consumeExpression(stops ...string) int {
	depth := 0
	end := p.peek().start
	for !p.eof() {
		token := p.peek()
		if depth == 0 && token.kind == punctToken && slices.Contains(stops, token.value) {
			break
		}
		if token.isGroupOpener() {
			depth++
		} else if token.isGroupCloser() {
			depth--
		}
		end = token.end
		p.advance()
	}

	return end
}

func (p *typeParser) consumeUntilPunct(value string) string {
	start := p.peek().start
	end := start
	for !p.eof() && !p.atPunct(value) {
		end = p.advance().end
	}

	return strings.Trim(p.source[start:max(start, end)], " \t\n\r\x00\x0b")
}

func (p *typeParser) lastConsumed() *lexeme {
	if p.pos == 0 {
		return &lexeme{kind: noToken}
	}
	token := p.tokens[p.pos-1]

	return &token
}

// consumeToStatementEnd reads to the statement's end: its `;`, or a line break after something that could end an
// expression and before something that does not continue one.
func (p *typeParser) consumeToStatementEnd(after *lexeme) int {
	depth := 0
	end := p.peek().start
	previous := after
	for !p.eof() {
		token := p.peek()
		if depth == 0 {
			if token.isPunct(";") {
				end = token.end
				p.advance()

				break
			}
			if previous != nil && couldEndAnExpression(*previous) && !continuesExpression(token) && strings.Contains(p.source[previous.end:token.start], "\n") {
				break
			}
		}
		if token.isGroupOpener() {
			depth++
		} else if token.isGroupCloser() {
			depth--
		}
		end = token.end
		current := token
		previous = &current
		p.advance()
	}

	return end
}

func couldEndAnExpression(token lexeme) bool {
	return token.isIdentifier("") || token.is(stringToken, "") || token.is(numberToken, "") || token.isGroupCloser()
}

func continuesExpression(token lexeme) bool {
	return token.kind == punctToken && !token.isGroupOpener() && !token.isGroupCloser() &&
		!slices.Contains([]string{";", ",", "!", "...", "@", "#", "++", "--"}, token.value)
}

func (p *typeParser) skipStatement() {
	if p.atPunct("{") {
		p.skipBlock()

		return
	}
	p.consumeToStatementEnd(nil)
}

func (p *typeParser) skipBlock() {
	if !p.atPunct("{") {
		return
	}
	depth := 0
	for !p.eof() {
		token := p.advance()
		if token.isPunct("{") {
			depth++
		} else if token.isPunct("}") {
			depth--
			if depth == 0 {
				return
			}
		}
	}
}

// skipGroup passes over a parenthesised group.
func (p *typeParser) skipGroup() {
	if !p.atPunct("(") {
		return
	}
	depth := 0
	for !p.eof() {
		token := p.advance()
		if token.isGroupOpener() {
			depth++
		} else if token.isGroupCloser() {
			depth--
			if depth == 0 {
				return
			}
		}
	}
}

// skipBody passes over a statement's body: a block, or one statement.
func (p *typeParser) skipBody() {
	if p.atPunct("{") {
		p.skipBlock()

		return
	}
	p.parseStatement()
}

func (p *typeParser) skipIf() {
	p.advance()
	p.skipGroup()
	p.skipBody()
	if p.atID("else") {
		p.advance()
		if p.atID("if") {
			p.skipIf()

			return
		}
		p.skipBody()
	}
}

func (p *typeParser) skipTry() {
	p.advance()
	p.skipBlock()
	if p.atID("catch") {
		p.advance()
		p.skipGroup()
		p.skipBlock()
	}
	if p.atID("finally") {
		p.advance()
		p.skipBlock()
	}
}
