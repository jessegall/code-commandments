package php

import (

	"github.com/jessegall/code-commandments/engine"
)

const (
	maxLookupDepth = 10
)

var factTypes = []string{"bool", "int", "float", "string", "array", "iterable"}

// LookupEnvy is the classes a codebase owns, read for methods that look a fact up about one of them in a keyed store of their own class: a question the object should answer.
type LookupEnvy struct {
	codebase *engine.Codebase
	owned    map[string]bool
}

var lookupEnvies = engine.Memoised(func(codebase *engine.Codebase) *LookupEnvy {
	envy := &LookupEnvy{codebase: codebase, owned: map[string]bool{}}
	for _, declaration := range In(codebase).Where(isClassLike).Get() {
		if name := declaration.Node().Symbol; name != "" {
			envy.owned[name] = true
		}
	}

	return envy
})

