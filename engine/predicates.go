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

// offered are each language's own checks a rule may name. A language offers them from its package's init, one
// package after another, so an offer is never made while another is.
var offered sync.Map

// Offers has the language offer its own checks to rules, under their public names.
func Offers(language contract.Language, predicates ...Predicate) {
	held, _ := offered.Load(language)
	previous, _ := held.([]Predicate)

	offered.Store(language, append(slices.Clone(previous), predicates...))
}

// PredicatesOf are the checks the language offers a rule, by name.
func PredicatesOf(language contract.Language) []Predicate {
	held, _ := offered.Load(language)
	predicates, _ := held.([]Predicate)

	sorted := slices.Clone(predicates)
	slices.SortFunc(sorted, func(a, b Predicate) int { return strings.Compare(a.Name, b.Name) })

	return sorted
}

// PredicateOf is the language's own check of the name.
func PredicateOf(language contract.Language, name string) (Predicate, bool) {
	predicates := PredicatesOf(language)

	at := slices.IndexFunc(predicates, func(predicate Predicate) bool { return predicate.Name == name })
	if at < 0 {
		return Predicate{}, false
	}

	return predicates[at], true
}
