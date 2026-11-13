package engine

import (
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// spoken is what one language registers with the engine, in the one table every language registers in: how its tree
// reads, how it names a scope and counts a body, the facts it fills once the codebase is read, the checks that declare
// a file frozen, and the predicates a rule may name.
type spoken struct {
	grammar    *Grammar
	namesScope func(Match) string
	runsBody   func(Match) bool
	fill       Filler
	freezers   []Freezer
	predicates []Predicate
}

// languages holds each language's row. A change copies the row and stores the copy, so a read never sees one half
// written.
var (
	languages   sync.Map
	registering sync.Mutex
	nothing     = &spoken{}
)

// registers changes a language's row.
func registers(language contract.Language, change func(*spoken)) {
	registering.Lock()
	defer registering.Unlock()

	row := *spokenIn(language)
	change(&row)
	languages.Store(language, &row)
}

// spokenIn is a language's row, empty for a language that registered nothing.
func spokenIn(language contract.Language) *spoken {
	if row, registered := languages.Load(language); registered {
		return row.(*spoken)
	}

	return nothing
}
