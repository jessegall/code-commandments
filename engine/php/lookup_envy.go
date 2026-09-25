package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const (
	// maxLookupDepth is how deep a read chain may run before it is navigation, not a lookup.
	maxLookupDepth = 10
)

// factTypes are the return types a lookup answers with: a fact, not an object.
var factTypes = []string{"bool", "int", "float", "string", "array", "iterable"}

// LookupEnvy is the classes a codebase owns, read for methods that look a fact up about one of them in a keyed store
// of their own class: a question the object should answer.
type LookupEnvy struct {
	codebase *engine.Codebase
	owned    map[string]bool
}

var lookupEnvies = Memoised(func(codebase *engine.Codebase) *LookupEnvy {
	envy := &LookupEnvy{codebase: codebase, owned: map[string]bool{}}
	for _, declaration := range In(codebase).Where(isClassLike).Get() {
		if name := declaration.Node().Symbol; name != "" {
			envy.owned[name] = true
		}
	}

	return envy
})

// LookupEnvyOf is the codebase's lookup-envy reading.
func LookupEnvyOf(codebase *engine.Codebase) *LookupEnvy {
	return lookupEnvies.Of(codebase)
}

// IsEnviedOwner says whether the method looks a fact up about another owned class.
func (e *LookupEnvy) IsEnviedOwner(method engine.Match) bool {
	return e.EnviedOwner(method) != ""
}

// EnviedOwner is the owned class a method returning a fact takes as its one owned parameter, uses only through its
// members, and returns a keyed read about from one of its own collaborators; empty otherwise.
func (e *LookupEnvy) EnviedOwner(method engine.Match) string {
	class := Node{Match: method}.EnclosingClassLike()
	body := method.ChildrenIn("stmts")
	if method.Kind() != "Stmt_ClassMethod" || !class.Exists() || len(body) == 0 && !hasStatements(method) {
		return ""
	}
	returns := strings.ToLower(Written(method.Node().Returns).SimpleName())
	if !slices.Contains(factTypes, returns) || buildsSomething(body) {
		return ""
	}
	name, owner := e.soleOwnedParam(method, class.Node().Symbol)
	if name == "" || !usedOnlyViaMembers(body, name) {
		return ""
	}
	if e.returnsAKeyedFetch(body, name, owner) {
		return owner
	}

	return ""
}

func (e *LookupEnvy) soleOwnedParam(method engine.Match, host string) (string, string) {
	found, owner := "", ""
	for _, param := range Params(method) {
		written, name := Written(param.Node().Declared).SimpleName(), variableName(param.Child("var"))
		if written == "" || written == host || !e.owned[written] || name == "" {
			continue
		}
		if found != "" {
			return "", ""
		}
		found, owner = name, written
	}

	return found, owner
}

func usedOnlyViaMembers(body []engine.Match, param string) bool {
	used := false
	for _, statement := range body {
		for _, variable := range withDescendants(statement) {
			if variable.Kind() != "Expr_Variable" || variable.Name() != param {
				continue
			}
			used = true
			if !isMemberAccess(variable.Parent()) || variable.Node().Field != "var" {
				return false
			}
		}
	}

	return used
}

func (e *LookupEnvy) returnsAKeyedFetch(body []engine.Match, param, owner string) bool {
	for _, statement := range body {
		for _, returned := range withDescendants(statement) {
			if returned.Kind() != "Stmt_Return" || !returned.Child("expr").Exists() {
				continue
			}
			for _, node := range withDescendants(returned.Child("expr")) {
				if e.isKeyedFetchRead(node, param, owner) {
					return true
				}
			}
		}
	}

	return false
}

func (e *LookupEnvy) isKeyedFetchRead(node engine.Match, param, owner string) bool {
	producer := node.Child("var")
	if !isMemberAccess(node) || !isMethodSend(producer) {
		return false
	}
	arguments := producer.ChildrenIn("args")

	return onCollaborator(producer) && anyArgUsesParamMember(arguments, param) && !e.everyKeyIsAnEnumField(arguments, param, owner) &&
		navigationDepth(node) <= maxLookupDepth
}

func (e *LookupEnvy) everyKeyIsAnEnumField(arguments []engine.Match, param, owner string) bool {
	var keys []engine.Match
	for _, argument := range arguments {
		if argument.Kind() != "Arg" {
			continue
		}
		for _, node := range withDescendants(argument.Child("value")) {
			if isMemberAccessOf(node, param) {
				keys = append(keys, node)
			}
		}
	}
	types, program := TypesOf(e.codebase), ProgramOf(e.codebase)

	return len(keys) > 0 && !slices.ContainsFunc(keys, func(key engine.Match) bool {
		name := key.Child("name")

		return key.Kind() != "Expr_PropertyFetch" || name.Kind() != "Identifier" || !program.IsEnum(types.PropertyTypeOf(owner, name.Name()))
	})
}

func anyArgUsesParamMember(arguments []engine.Match, param string) bool {
	return slices.ContainsFunc(arguments, func(argument engine.Match) bool {
		return argument.Kind() == "Arg" && slices.ContainsFunc(withDescendants(argument.Child("value")), func(node engine.Match) bool {
			return isMemberAccessOf(node, param)
		})
	})
}

// onCollaborator says whether a call is sent to a property of $this, however deep.
func onCollaborator(call engine.Match) bool {
	receiver := call.Child("var")
	if !isPropertyRead(receiver) {
		return false
	}
	for isPropertyRead(receiver) {
		receiver = receiver.Child("var")
	}

	return variableName(receiver) == "this"
}

func navigationDepth(expr engine.Match) int {
	depth := 0
	for isMemberAccess(expr) && depth <= maxLookupDepth {
		depth++
		expr = expr.Child("var")
	}

	return depth
}
