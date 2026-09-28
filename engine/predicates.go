package engine

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Predicate is a check of a language's own that a rule may name: a public name that stays when the method behind
// it changes, what it says of a node, and the question it asks.
type Predicate struct {
	Name  string
	Says  string
	Holds func(Match) bool
}

// predicates are each language's own checks a rule may name.
var predicates = struct {
	sync.Mutex
	of map[contract.Language][]Predicate
}{of: map[contract.Language][]Predicate{}}

// Predicates offers the checks of a language's own to rules, under their public names.
func Predicates(language contract.Language, offered ...Predicate) {
	predicates.Lock()
	defer predicates.Unlock()

	predicates.of[language] = append(predicates.of[language], offered...)
}

// PredicatesOf are the checks of the language's own a rule may name, by name.
func PredicatesOf(language contract.Language) []Predicate {
	predicates.Lock()
	defer predicates.Unlock()

	offered := slices.Clone(predicates.of[language])
	slices.SortFunc(offered, func(a, b Predicate) int { return strings.Compare(a.Name, b.Name) })

	return offered
}

// PredicateOf is the language's own check of the name.
func PredicateOf(language contract.Language, name string) (Predicate, bool) {
	for _, predicate := range PredicatesOf(language) {
		if predicate.Name == name {
			return predicate, true
		}
	}

	return Predicate{}, false
}
