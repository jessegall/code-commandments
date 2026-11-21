package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var reactive = []string{"ref", "computed", "shallowRef", "toRef", "customRef", "reactive"}

// Module is a script's top-level statements, read as the PHP tool reads a script: its imports, its variables, functions and classes, its type declarations and the calls it makes at the top.
type Module struct {
	statements []Node
}

func ModuleOf(statements []engine.Match) Module {
	module := Module{}
	for _, statement := range statements {
		module.statements = append(module.statements, Node{statement})
	}

	return module
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

func (m Module) ImportOf(name string) (Import, bool) {
	for _, imported := range m.Imports() {
		if _, binds := imported.Bindings[name]; binds && imported.HasFrom {
			return imported, true
		}
	}

	return Import{}, false
}

// Call is a call at the top of a script, or the one a variable is initialised with: to a plain name.
type Call struct {
	Callee        string
	TypeArguments []TypeNode
	Arguments     []Node
}

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

func variableOf(statement Node) (Variable, bool) {
	list := Node{statement.Child("declarationList")}
	declarations := list.ChildrenIn("declarations")
	keyword := strings.Fields(list.Written())
	if statement.Kind() != "VariableStatement" || len(declarations) == 0 || len(keyword) == 0 {
		return Variable{}, false
	}
	declaration := Node{declarations[0]}
	name := Node{declaration.Child("name")}
	variable := Variable{Keyword: keyword[0]}
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

// Returned is a key of a returned object literal and the local it returns, empty when its value is anything else.
type Returned struct {
	Key, Local string
}

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

func (m Module) Variable(name string) (Variable, bool) {
	for _, statement := range m.statements {
		if variable, ok := variableOf(statement); ok && slices.Contains(variable.Names, name) {
			return variable, true
		}
	}

	return Variable{}, false
}

// TypeDeclaration is an interface or a type alias.
type TypeDeclaration struct {
	Name      string
	Header    string
	Interface bool
	Members   []Member
	Type      TypeNode
}

