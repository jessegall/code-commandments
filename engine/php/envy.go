package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var collectionQueries = map[string]int{"in_array": 1, "array_search": 1, "array_filter": 0, "array_reduce": 0, "array_column": 0, "array_sum": 0}

// Envy is what the codebase declares that feature envy is read against: the classes it owns, each class-like's methods, and each class's parent.
type Envy struct {
	codebase  *engine.Codebase
	owned     map[string]bool
	contracts map[string]map[string]bool
	parents   map[string]string
}

var envies = engine.Memoised(func(codebase *engine.Codebase) *Envy {
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

