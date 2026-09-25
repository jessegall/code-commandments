package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// collectionQueries are the functions that search a collection, by the position they take it at.
var collectionQueries = map[string]int{"in_array": 1, "array_search": 1, "array_filter": 0, "array_reduce": 0, "array_column": 0, "array_sum": 0}

// Envy is what the codebase declares that feature envy is read against: the classes it owns, each class-like's
// methods, and each class's parent.
type Envy struct {
	codebase  *engine.Codebase
	owned     map[string]bool
	contracts map[string]map[string]bool
	parents   map[string]string
}

var envies = Memoised(func(codebase *engine.Codebase) *Envy {
	envy := &Envy{codebase: codebase, owned: map[string]bool{}, contracts: map[string]map[string]bool{}, parents: map[string]string{}}
	for _, declaration := range In(codebase).Where(isClassLike).Get() {
		name := declaration.Node().Symbol
		if name == "" {
			continue
		}
		methods := map[string]bool{}
		for _, method := range Methods(declaration) {
			methods[strings.ToLower(method.Name())] = true
		}
		envy.contracts[name] = methods
		if declaration.Kind() == "Stmt_Interface" {
			continue
		}
		envy.owned[name] = true
		if extends := declaration.Child("extends"); declaration.Kind() == "Stmt_Class" && isName(extends) {
			envy.parents[name] = extends.Name()
		}
	}

	return envy
})

// EnvyOf is the codebase's feature-envy reading.
func EnvyOf(codebase *engine.Codebase) *Envy {
	return envies.Of(codebase)
}

// IsEnviedOwner says whether the method envies another owned class; boundary says which classes are framework entry
// points, whose collections a method may query.
func (e *Envy) IsEnviedOwner(method engine.Match, boundary func(class string) bool) bool {
	return e.EnviedOwner(method, boundary) != ""
}

// EnviedOwner is the owned class the method does the work of: it queries that class's collection, or reaches
// through one parameter of that class more than through itself, looping its structure or writing its fields; empty
// when it envies nothing. A class filling in a contract, and a method that builds a class, envy nothing.
func (e *Envy) EnviedOwner(method engine.Match, boundary func(class string) bool) string {
	node := Node{Match: method}
	class := node.EnclosingClassLike()
	body := method.ChildrenIn("stmts")
	if method.Kind() != "Stmt_ClassMethod" || len(body) == 0 && !hasStatements(method) || !class.Exists() {
		return ""
	}
	if implementsAnInterface(class.Match) || e.fulfilsContract(class.Match, method.Name()) || buildsSomething(body) {
		return ""
	}
	host := class.Node().Symbol
	if queried := e.queriedOwner(method, body, host, boundary); queried != "" {
		return queried
	}
	paramTypes := e.ownedParamTypes(method, host)
	if len(paramTypes) == 0 {
		return ""
	}
	own, foreign := countReaches(body, paramTypes)
	if len(foreign) != 1 {
		return ""
	}
	var param string
	for each := range foreign {
		param = each
	}
	if foreign[param] <= own {
		return ""
	}
	iterates, mutates := traversesStructureOf(body, param), mutatesMembersOf(body, param)
	if iterates && !mutates && delegatesElementToCollaborator(method, body, param) {
		return ""
	}
	if iterates || mutates {
		return paramTypes[param]
	}

	return ""
}

// hasStatements says whether a method declares a body; an abstract one has none.
func hasStatements(method engine.Match) bool {
	return !slices.Contains(method.Node().Modifiers, "abstract") && (Node{Match: method}).EnclosingClassLike().Kind() != "Stmt_Interface"
}

func implementsAnInterface(class engine.Match) bool {
	return (class.Kind() == "Stmt_Class" || class.Kind() == "Stmt_Enum") && len(class.ChildrenIn("implements")) > 0
}

func (e *Envy) fulfilsContract(class engine.Match, method string) bool {
	name := strings.ToLower(method)
	if class.Kind() == "Stmt_Class" || class.Kind() == "Stmt_Enum" {
		for _, contract := range class.ChildrenIn("implements") {
			if e.contracts[contract.Name()][name] {
				return true
			}
		}
	}
	parent := ""
	if extends := class.Child("extends"); class.Kind() == "Stmt_Class" && isName(extends) {
		parent = extends.Name()
	}
	seen := map[string]bool{}
	for parent != "" && !seen[parent] {
		seen[parent] = true
		if e.contracts[parent][name] {
			return true
		}
		parent = e.parents[parent]
	}

	return false
}

// buildsSomething says whether statements build a new object or call a static method on a named class.
func buildsSomething(body []engine.Match) bool {
	for _, statement := range body {
		for _, node := range withDescendants(statement) {
			if node.Kind() == "Expr_New" || node.Kind() == "Expr_StaticCall" && isName(node.Child("class")) {
				return true
			}
		}
	}

	return false
}

func (e *Envy) queriedOwner(method engine.Match, body []engine.Match, host string, boundary func(string) bool) string {
	paramTypes := allParamTypes(method)
	if len(paramTypes) == 0 {
		return ""
	}
	chains := ChainsOf(e.codebase)
	for _, statement := range body {
		for _, call := range withDescendants(statement) {
			function := call.Child("name")
			if call.Kind() != "Expr_FuncCall" || !isName(function) {
				continue
			}
			position, ok := collectionQueries[strings.ToLower(ShortName(function.Name()))]
			if !ok {
				continue
			}
			passed := call.ChildrenIn("args")
			if position >= len(passed) || passed[position].Kind() != "Arg" || !isCollectionAccess(passed[position].Child("value")) {
				continue
			}
			owner := chains.Resolve(passed[position].Child("value").Child("var"), paramTypes)
			if owner != "" && owner != host && e.owned[owner] && !boundary(owner) {
				return owner
			}
		}
	}

	return ""
}

func isCollectionAccess(node engine.Match) bool {
	return isPropertyRead(node) || isMethodSend(node) && len(node.ChildrenIn("args")) == 0
}

// allParamTypes is each named parameter's written type, by its simple name.
func allParamTypes(method engine.Match) map[string]string {
	types := map[string]string{}
	for _, param := range Params(method) {
		if written, name := Written(param.Node().Declared).SimpleName(), variableName(param.Child("var")); written != "" && name != "" {
			types[name] = written
		}
	}

	return types
}

func (e *Envy) ownedParamTypes(method engine.Match, host string) map[string]string {
	types := map[string]string{}
	for name, written := range allParamTypes(method) {
		if written != host && e.owned[written] {
			types[name] = written
		}
	}

	return types
}

func isMemberAccess(node engine.Match) bool {
	return isPropertyRead(node) || isMethodSend(node)
}

func isMemberAccessOf(expr engine.Match, param string) bool {
	return isMemberAccess(expr) && expr.Child("var").Kind() == "Expr_Variable" && expr.Child("var").Name() == param
}

// countReaches is how often the body reaches into $this, and into each candidate parameter.
func countReaches(body []engine.Match, candidates map[string]string) (int, map[string]int) {
	own, foreign := 0, map[string]int{}
	for _, statement := range body {
		for _, node := range withDescendants(statement) {
			receiver := node.Child("var")
			if !isMemberAccess(node) || receiver.Kind() != "Expr_Variable" || receiver.Name() == "" {
				continue
			}
			if receiver.Name() == "this" {
				own++
			} else if _, candidate := candidates[receiver.Name()]; candidate {
				foreign[receiver.Name()]++
			}
		}
	}

	return own, foreign
}

func traversesStructureOf(body []engine.Match, param string) bool {
	for _, statement := range body {
		for _, loop := range withDescendants(statement) {
			if loop.Kind() == "Stmt_Foreach" && isMemberAccessOf(loop.Child("expr"), param) {
				return true
			}
		}
	}

	return false
}

func mutatesMembersOf(body []engine.Match, param string) bool {
	for _, statement := range body {
		for _, node := range withDescendants(statement) {
			var target engine.Match
			switch {
			case node.Kind() == "Expr_Assign" || strings.HasPrefix(node.Kind(), "Expr_AssignOp_"):
				target = node.Child("var")
			case node.Kind() == "Expr_PreInc" || node.Kind() == "Expr_PostInc" || node.Kind() == "Expr_PreDec" || node.Kind() == "Expr_PostDec":
				target = node.Child("var")
			default:
				continue
			}
			if target.Kind() == "Expr_ArrayDimFetch" {
				target = target.Child("var")
			}
			if isMemberAccessOf(target, param) {
				return true
			}
		}
	}

	return false
}

// delegatesElementToCollaborator says whether a loop over the parameter's structure hands each element to the
// method's own class or another of its parameters: orchestration, not envy.
func delegatesElementToCollaborator(method engine.Match, body []engine.Match, param string) bool {
	self := method.Name()
	var collaborators []string
	for name := range allParamTypes(method) {
		if name != param {
			collaborators = append(collaborators, name)
		}
	}
	for _, statement := range body {
		for _, loop := range withDescendants(statement) {
			element := loop.Child("valueVar")
			if loop.Kind() != "Stmt_Foreach" || !isMemberAccessOf(loop.Child("expr"), param) || element.Kind() != "Expr_Variable" || element.Name() == "" {
				continue
			}
			for _, inner := range loop.ChildrenIn("stmts") {
				for _, call := range withDescendants(inner) {
					if call.Kind() != "Expr_MethodCall" && call.Kind() != "Expr_StaticCall" {
						continue
					}
					if call.Kind() == "Expr_MethodCall" && call.Child("name").Kind() == "Identifier" && call.Child("name").Name() == self && variableName(call.Child("var")) == "this" {
						continue
					}
					involved := variablesInCall(call)
					if !slices.Contains(involved, element.Name()) {
						continue
					}
					if slices.Contains(involved, "this") || slices.ContainsFunc(collaborators, func(name string) bool { return slices.Contains(involved, name) }) {
						return true
					}
				}
			}
		}
	}

	return false
}

func variablesInCall(call engine.Match) []string {
	var names []string
	receiver := call.Child("var")
	if call.Kind() == "Expr_StaticCall" {
		receiver = call.Child("class")
	}
	for receiver.Kind() == "Expr_PropertyFetch" {
		receiver = receiver.Child("var")
	}
	if name := variableName(receiver); name != "" {
		names = append(names, name)
	}
	for _, argument := range call.ChildrenIn("args") {
		for _, variable := range withDescendants(argument) {
			if name := variableName(variable); name != "" {
				names = append(names, name)
			}
		}
	}

	return names
}
