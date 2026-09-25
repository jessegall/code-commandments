package php

import (
	"slices"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// sends are the call kinds the call graph reads, in the order it reads them.
var sends = []string{"Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall"}

// Index is the program's call graph: every named method send and static call, by the member it names.
type Index struct {
	program *Program
	byName  map[string][]engine.Match
}

var indexes sync.Map

// IndexOf is the codebase's call graph, built on first need.
func IndexOf(codebase *engine.Codebase) *Index {
	if index, ok := indexes.Load(codebase); ok {
		return index.(*Index)
	}
	index, _ := indexes.LoadOrStore(codebase, buildIndex(codebase))

	return index.(*Index)
}

func buildIndex(codebase *engine.Codebase) *Index {
	index := &Index{program: ProgramOf(codebase), byName: map[string][]engine.Match{}}
	files := codebase.Of(contract.PHP).Files()
	for _, kind := range sends {
		for _, file := range files {
			for _, node := range file.Nodes() {
				if node.Kind != kind {
					continue
				}
				if name := child(node, "name"); name.Kind == "Identifier" {
					index.byName[name.Name] = append(index.byName[name.Name], file.Match(node.ID))
				}
			}
		}
	}

	return index
}

// CallersOf is every call of the method whose receiver is the class or a subclass of it: a static call's class, or
// a method send's receiver as its scope declares it.
func (i *Index) CallersOf(fqcn, method string) []engine.Match {
	var callers []engine.Match
	for _, call := range i.byName[method] {
		receiver := staticCallClass(call)
		if receiver == "" {
			receiver = ReceiverTypeOf(call)
		}
		if receiver != "" && (receiver == fqcn || i.program.Extends(receiver, fqcn)) {
			callers = append(callers, call)
		}
	}

	return callers
}

// staticCallClass is the class a static call names, `self` and `static` read as the class they sit in.
func staticCallClass(call engine.Match) string {
	class := call.Child("class")
	if call.Kind() != "Expr_StaticCall" || !isName(class) {
		return ""
	}
	if slices.Contains([]string{"self", "static"}, class.Name()) {
		return EnclosingClassName(call)
	}

	return class.Name()
}
