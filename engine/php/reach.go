package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// A resource is named by what it is: a class by its name, a function or a constant by a prefix.
const (
	functionResource = "fn:"
	constantResource = "const:"
)

// ResourceReach is what each class and each scope reaches: the classes it names, the functions it calls and the
// constants it reads; a scope leaves out the classes its signatures name, which describe it rather than do anything.
type ResourceReach struct {
	Classes  engine.ResourcePopulation
	Scopes   engine.ResourcePopulation
	codebase *engine.Codebase
}

var reaches = Memoised(func(codebase *engine.Codebase) *ResourceReach {
	byClass, byScope := map[string]map[string]bool{}, map[string]map[string]bool{}
	link := func(edges map[string]map[string]bool, from, target string) {
		if from == "" || target == "" || from == target {
			return
		}
		if edges[from] == nil {
			edges[from] = map[string]bool{}
		}
		edges[from][strings.TrimLeft(target, `\`)] = true
	}
	php := In(codebase)
	for _, reference := range php.Where(engine.As(Node.IsClassReference)).Get() {
		node := Node{Match: reference}
		link(byClass, EnclosingClassName(reference), reference.Name())
		if !node.IsSignatureType() {
			link(byScope, ScopeOf(reference), reference.Name())
		}
	}
	for _, call := range php.WhereKind("Expr_FuncCall").Get() {
		if name := call.Child("name"); isName(name) {
			link(byClass, EnclosingClassName(call), functionResource+name.Name())
			link(byScope, ScopeOf(call), functionResource+name.Name())
		}
	}
	for _, constant := range php.WhereKind("Expr_ConstFetch").Get() {
		if name := constant.Child("name").Name(); !slicesContainsFold([]string{"true", "false", "null"}, name) {
			link(byClass, EnclosingClassName(constant), constantResource+name)
			link(byScope, ScopeOf(constant), constantResource+name)
		}
	}

	return &ResourceReach{Classes: engine.Counting(byClass), Scopes: engine.Counting(byScope), codebase: codebase}
})

// ReachOf is the codebase's resource reach.
func ReachOf(codebase *engine.Codebase) *ResourceReach {
	return reaches.Of(codebase)
}

// IsType says whether a resource is a class rather than a function call.
func (r *ResourceReach) IsType(resource string) bool {
	return !strings.HasPrefix(resource, functionResource)
}

// IsTerminal says whether a resource is declared outside the codebase.
func (r *ResourceReach) IsTerminal(resource string) bool {
	_, declared := ProgramOf(r.codebase).Declaration(resource)

	return !declared
}

// ScopeOf is where a node is written, as Class::method, the class alone outside a method, (file) outside a class; an
// anonymous class's scope is prefixed with its file.
func ScopeOf(node engine.Match) string {
	class := EnclosingClassName(node)
	scope := class
	if scope == "" {
		scope = "(file)"
	}
	if method := EnclosingFunctionName(node); method != "" {
		scope += "::" + method
	}
	if class == "" && (Node{Match: node}).EnclosingClassLike().Exists() {
		return node.File() + ":" + scope
	}

	return scope
}

// IsSignatureType says whether the node sits in a function's signature: a parameter, an attribute, or its return
// type.
func (n Node) IsSignatureType() bool {
	function := n.EnclosingFunctionLike()
	if !function.Exists() {
		return false
	}
	for at := n.Up(); at.Exists(); at = at.Up() {
		if at.Kind() == "Param" || at.Kind() == "Attribute" {
			return true
		}
	}
	for at := n; at.Exists() && at.Node() != function.Node(); at = at.Up() {
		if at.Node().Field == "returnType" && at.Up().Node() == function.Node() {
			return true
		}
	}

	return false
}
