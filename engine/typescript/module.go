package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// reactive are the calls that wrap a value in a ref, whose type is what the call holds.
var reactive = []string{"ref", "computed", "shallowRef", "toRef", "customRef", "reactive"}

// Module is a script's top-level statements, read as the PHP tool reads a script: its imports, its variables,
// functions and classes, its type declarations and the calls it makes at the top.
type Module struct {
	statements []Node
}

// ModuleOf reads the statements.
func ModuleOf(statements []engine.Match) Module {
	module := Module{}
	for _, statement := range statements {
		module.statements = append(module.statements, Node{statement})
	}

	return module
}

// ModuleOfNodes reads the statements.
func ModuleOfNodes(statements []Node) Module {
	return Module{statements: statements}
}

// Import is an import: the names it binds, each to what it imports, where from, and the statement as written.
type Import struct {
	Names     []string
	Bindings  map[string]string
	Specifier string
	HasFrom   bool
	Resolves  string
	Statement string
}

// BindsAny says whether the import binds a name the test accepts.
func (i Import) BindsAny(test func(string) bool) bool {
	return slices.ContainsFunc(i.Names, test)
}

// Imports is every import of the script, each statement ended with a `;`.
func (m Module) Imports() []Import {
	var imports []Import
	for _, statement := range m.statements {
		if imported, ok := importOf(statement); ok {
			imports = append(imports, imported)
		}
	}

	return imports
}

func importOf(statement Node) (Import, bool) {
	imported := Import{Bindings: map[string]string{}, Resolves: statement.Resolves()}
	bind := func(local, name string) {
		if _, seen := imported.Bindings[local]; !seen {
			imported.Names = append(imported.Names, local)
		}
		imported.Bindings[local] = name
	}
	switch statement.Kind() {
	case "ImportDeclaration":
		clause := statement.Child("importClause")
		if clause.Name() != "" {
			bind(clause.Name(), "default")
		}
		bindings := clause.Child("namedBindings")
		if bindings.Kind() == "NamespaceImport" {
			bind(bindings.Name(), "*")
		}
		for _, element := range bindings.ChildrenIn("elements") {
			name := element.Name()
			if element.Child("propertyName").Exists() {
				name = element.Child("propertyName").Name()
			}
			bind(element.Name(), name)
		}
		if specifier, ok := statement.Child("moduleSpecifier").Text(); ok {
			imported.Specifier, imported.HasFrom = specifier, true
		}
	case "ImportEqualsDeclaration":
		bind(statement.Name(), statement.Child("moduleReference").Written())
	default:
		return Import{}, false
	}
	imported.Statement = strings.TrimSpace(statement.Written())
	if !strings.HasSuffix(imported.Statement, ";") {
		imported.Statement += ";"
	}

	return imported, true
}

// ImportOf is the import that binds the name from a module.
func (m Module) ImportOf(name string) (Import, bool) {
	for _, imported := range m.Imports() {
		if _, binds := imported.Bindings[name]; binds && imported.HasFrom {
			return imported, true
		}
	}

	return Import{}, false
}

// ReExports is the file of every module the script re-exports from, once.
func (m Module) ReExports() []string {
	var files []string
	for _, statement := range m.statements {
		if statement.Kind() == "ExportDeclaration" && statement.Resolves() != "" && !slices.Contains(files, statement.Resolves()) {
			files = append(files, statement.Resolves())
		}
	}

	return files
}

// Call is a call at the top of a script, or the one a variable is initialised with: to a plain name.
type Call struct {
	Callee        string
	TypeArguments []TypeNode
	Arguments     []Node
}

// CallOf is the call the expression makes to a plain name.
func CallOf(n Node) (Call, bool) {
	callee, ok := n.CalleeName()
	if !ok {
		return Call{}, false
	}
	call := Call{Callee: callee, TypeArguments: typesOf(n.ChildrenIn("typeArguments"))}
	for _, argument := range n.ChildrenIn("arguments") {
		call.Arguments = append(call.Arguments, Node{argument})
	}

	return call, true
}

// Variable is a `const`/`let`/`var` declaration, its first declarator.
type Variable struct {
	Keyword       string
	Pattern       string
	Names         []string
	ObjectPattern bool
	NamePattern   bool
	Annotation    TypeNode
	Initializer   Node
	InitCall      *Call
	Arrow         *FunctionType
}

// Render is the declaration as written again: `const name: T = init;`.
func (v Variable) Render() string {
	typed, init := "", ""
	if v.Annotation != nil {
		typed = ": " + v.Annotation.Render()
	}
	if v.Initializer.Exists() {
		init = " = " + strings.TrimRight(strings.TrimSpace(v.Initializer.Source()), ";")
	}

	return v.Keyword + " " + v.Pattern + typed + init + ";"
}

func variableOf(statement Node) (Variable, bool) {
	list := Node{statement.Child("declarationList")}
	declarations := list.ChildrenIn("declarations")
	if statement.Kind() != "VariableStatement" || len(declarations) == 0 {
		return Variable{}, false
	}
	declaration := Node{declarations[0]}
	name := Node{declaration.Child("name")}
	variable := Variable{Keyword: strings.Fields(list.Written())[0]}
	variable.Pattern, variable.Names = Pattern(name)
	variable.ObjectPattern = name.Kind() == "ObjectBindingPattern"
	variable.NamePattern = name.Kind() == "Identifier"
	if declaration.Child("type").Exists() {
		variable.Annotation = TypeOf(Node{declaration.Child("type")})
	}
	initializer := Node{declaration.Child("initializer")}
	if initializer.Kind() == "AwaitExpression" {
		initializer = Node{initializer.Child("expression")}
	}
	variable.Initializer = initializer
	if call, ok := CallOf(initializer); ok {
		variable.InitCall = &call
	}
	if arrow, ok := parenthesisedArrow(initializer); ok {
		variable.Arrow = &arrow
	}

	return variable, true
}

// parenthesisedArrow is the signature of an arrow function that writes its parameters in parentheses, its return
// type left out when it declares none.
func parenthesisedArrow(n Node) (FunctionType, bool) {
	written := strings.TrimSpace(strings.TrimPrefix(strings.TrimSpace(n.Written()), "async"))
	if n.Kind() != "ArrowFunction" || !strings.HasPrefix(written, "(") {
		return FunctionType{}, false
	}
	params, ok := paramsOf(n.ChildrenIn("parameters"))
	if !ok {
		return FunctionType{}, false
	}
	arrow := FunctionType{Params: params}
	if n.Child("type").Exists() {
		arrow.Returns = TypeOf(Node{n.Child("type")})
	}

	return arrow, true
}

// Function is a function declaration.
type Function struct {
	Name         string
	Params       []Param
	ReturnType   TypeNode
	ReturnObject []Returned
	Body         Module
}

// Signature is the function's type: its parameters and its return, void when it declares none.
func (f Function) Signature() FunctionType {
	var returns TypeNode = KeywordType{Name: "void"}
	if f.ReturnType != nil {
		returns = f.ReturnType
	}

	return FunctionType{Params: f.Params, Returns: returns}
}

func functionOf(statement Node) (Function, bool) {
	if statement.Kind() != "FunctionDeclaration" || statement.Name() == "" {
		return Function{}, false
	}
	params, ok := paramsOf(statement.ChildrenIn("parameters"))
	if !ok {
		return Function{}, false
	}
	function := Function{Name: statement.Name(), Params: params}
	if statement.Child("type").Exists() {
		function.ReturnType = TypeOf(Node{statement.Child("type")})
	}
	body := statement.Child("body")
	function.Body = ModuleOf(body.ChildrenIn("statements"))
	for _, returned := range body.ChildrenIn("statements") {
		object := Node{returned.Child("expression")}
		if returned.Kind() == "ReturnStatement" && object.Kind() == "ObjectLiteralExpression" {
			function.ReturnObject = objectShape(object)
		}
	}

	return function, true
}

// Returned is a key of a returned object literal and the local it returns, empty when its value is anything else.
type Returned struct {
	Key, Local string
}

// objectShape is a returned object literal's keys, each with the local it returns, a key written again keeping its
// place.
func objectShape(object Node) []Returned {
	var shape []Returned
	set := func(key, local string) {
		for index := range shape {
			if shape[index].Key == key {
				shape[index].Local = local

				return
			}
		}
		shape = append(shape, Returned{Key: key, Local: local})
	}
	for _, property := range object.ChildrenIn("properties") {
		key := property.Child("name")
		if key.Kind() != "Identifier" && key.Kind() != "StringLiteral" {
			continue
		}
		switch value := (Node{property.Child("initializer")}); property.Kind() {
		case "PropertyAssignment":
			local := ""
			if value.Kind() == "Identifier" {
				local = value.Name()
			}
			set(key.Written(), local)
		case "ShorthandPropertyAssignment", "MethodDeclaration", "GetAccessor", "SetAccessor":
			set(key.Written(), key.Written())
		}
	}

	return shape
}

// Variable is the declaration of a variable binding the name.
func (m Module) Variable(name string) (Variable, bool) {
	for _, statement := range m.statements {
		if variable, ok := variableOf(statement); ok && slices.Contains(variable.Names, name) {
			return variable, true
		}
	}

	return Variable{}, false
}

// Function is the function declared under the name.
func (m Module) Function(name string) (Function, bool) {
	for _, statement := range m.statements {
		if function, ok := functionOf(statement); ok && function.Name == name {
			return function, true
		}
	}

	return Function{}, false
}

// Call is the first call to the callee at the top of the script, a variable's initialiser included.
func (m Module) Call(callee string) (Call, bool) {
	for _, statement := range m.statements {
		if call, ok := CallOf(Node{statement.Child("expression")}); statement.Kind() == "ExpressionStatement" && ok && call.Callee == callee {
			return call, true
		}
		if variable, ok := variableOf(statement); ok && variable.InitCall != nil && variable.InitCall.Callee == callee {
			return *variable.InitCall, true
		}
	}

	return Call{}, false
}

// LocalNames is every name the script's top declares, once, in order: its variables, functions and classes.
func (m Module) LocalNames() []string {
	var names []string
	add := func(declared ...string) {
		for _, name := range declared {
			if name != "" && !slices.Contains(names, name) {
				names = append(names, name)
			}
		}
	}
	for _, statement := range m.statements {
		if variable, ok := variableOf(statement); ok {
			add(variable.Names...)
		}
		if statement.Kind() == "FunctionDeclaration" || statement.Kind() == "ClassDeclaration" {
			add(statement.Name())
		}
	}

	return names
}

// VariableNamedFrom is the name of the first plain `const name = …` whose initialiser call the test accepts.
func (m Module) VariableNamedFrom(test func(Call) bool) (string, bool) {
	for _, statement := range m.statements {
		if variable, ok := variableOf(statement); ok && variable.NamePattern && variable.InitCall != nil && test(*variable.InitCall) {
			return variable.Pattern, true
		}
	}

	return "", false
}

// DeclaredType is the type the script declares or soundly implies for a name: a function's signature, a variable's
// annotation, an arrow's signature, a reactive wrapper's value, or an initialiser's inferred type.
func (m Module) DeclaredType(name string) (TypeNode, bool) {
	if function, ok := m.Function(name); ok {
		return function.Signature(), true
	}
	variable, ok := m.Variable(name)
	switch {
	case !ok:
		return nil, false
	case variable.Annotation != nil:
		return variable.Annotation, true
	case variable.Arrow != nil:
		arrow := *variable.Arrow
		if arrow.Returns == nil {
			arrow.Returns = KeywordType{Name: "void"}
		}

		return arrow, true
	case variable.InitCall != nil:
		return reactiveType(*variable.InitCall)
	case variable.Initializer.Exists():
		return variable.Initializer.InferType()
	}

	return nil, false
}

// reactiveType is what a reactive wrapper holds: its type argument, a computed's return, or its value's type.
func reactiveType(call Call) (TypeNode, bool) {
	if !slices.Contains(reactive, call.Callee) {
		return nil, false
	}
	if len(call.TypeArguments) > 0 {
		return call.TypeArguments[0], true
	}
	if len(call.Arguments) == 0 {
		return nil, false
	}
	if call.Callee == "computed" {
		return call.Arguments[0].ReturnType()
	}

	return call.Arguments[0].InferType()
}

// StaticConst is a plain `const NAME = …` that calls nothing, as written again: a constant a component can carry in.
func (m Module) StaticConst(name string) (string, bool) {
	variable, ok := m.Variable(name)
	if !ok || variable.Keyword != "const" || variable.InitCall != nil || !variable.NamePattern {
		return "", false
	}

	return variable.Render(), true
}

// DestructuredCall is the call a destructured name comes from: `const { x } = useThing()`.
func (m Module) DestructuredCall(name string) (string, bool) {
	variable, ok := m.Variable(name)
	if !ok || !variable.ObjectPattern || variable.InitCall == nil {
		return "", false
	}

	return variable.InitCall.Callee, true
}

// ReturnTypeName is the return type a function declares, printed.
func (m Module) ReturnTypeName(function string) (TypeNode, bool) {
	if declared, ok := m.Function(function); ok {
		return declared.ReturnType, declared.ReturnType != nil
	}
	variable, ok := m.Variable(function)
	if !ok || variable.Arrow == nil || variable.Arrow.Returns == nil {
		return nil, false
	}

	return variable.Arrow.Returns, true
}

// InferredReturnFields is the type of each field a composable returns, read from the locals it returns.
func (m Module) InferredReturnFields(function string) Fields {
	declared, ok := m.Function(function)
	if !ok || declared.ReturnObject == nil {
		return Fields{}
	}
	var fields Fields
	for _, returned := range declared.ReturnObject {
		if returned.Local == "" {
			continue
		}
		if typed, ok := declared.Body.DeclaredType(returned.Local); ok {
			fields.Set(returned.Key, typed.UnwrapRef())
		}
	}

	return fields
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

func declarationOf(statement Node) (TypeDeclaration, bool) {
	name := statement.Child("name")
	switch statement.Kind() {
	case "InterfaceDeclaration":
		declaration := TypeDeclaration{Name: statement.Name(), Header: headerOf(statement, name, "{"), Interface: true}
		for _, member := range statement.ChildrenIn("members") {
			if read, ok := memberOf(Node{member}); ok {
				declaration.Members = append(declaration.Members, read)
			}
		}

		return declaration, true
	case "TypeAliasDeclaration":
		return TypeDeclaration{Name: statement.Name(), Header: headerOf(statement, name, "="), Type: TypeOf(Node{statement.Child("type")})}, true
	}

	return TypeDeclaration{}, false
}

// headerOf is what a declaration writes between its name and the opener: its type parameters and what it extends.
func headerOf(statement Node, name engine.Match, opener string) string {
	whole, err := statement.Span()
	named, nameErr := name.Span()
	if err != nil || nameErr != nil {
		return ""
	}
	rest := string(whole.Source[named.End:whole.End])
	if end := strings.Index(rest, opener); end >= 0 {
		rest = rest[:end]
	}

	return strings.TrimSpace(rest)
}

// TypeDeclaration is the interface declared under the name, else the type alias.
func (m Module) TypeDeclaration(name string) (TypeDeclaration, bool) {
	for _, wantInterface := range []bool{true, false} {
		for _, statement := range m.statements {
			if declaration, ok := declarationOf(statement); ok && declaration.Interface == wantInterface && declaration.Name == name {
				return declaration, true
			}
		}
	}

	return TypeDeclaration{}, false
}

// TypeFields is the members of the type the script declares under the name.
func (m Module) TypeFields(name string) Fields {
	declaration, declared := m.TypeDeclaration(name)
	if !declared {
		return Fields{}
	}

	return declaration.Fields()
}

// LocalTypes is each declaration the names reach within the script, the ones they reference followed in turn, in
// the order they were reached.
func (m Module) LocalTypes(names []string) []TypeDeclaration {
	var reached []TypeDeclaration
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
		reached = append(reached, declaration)
		queue = append(queue, declaration.References()...)
	}

	return reached
}

// FieldType is a declared type's field's type, its reactive wrapper taken off.
func (m Module) FieldType(typeName, field string) (TypeNode, bool) {
	typed, ok := m.TypeFields(typeName).Get(field)
	if !ok {
		return nil, false
	}

	return typed.UnwrapRef(), true
}
