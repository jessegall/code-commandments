package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Predicate is a check of a language's own that a rule may name: a public name that stays when the method behind it changes, what it says of a node, and the question it asks.
type Predicate struct {
	Name  string
	Says  string
	Holds func(Match) bool
}

func Offers(language contract.Language, predicates ...Predicate) {
	registers(language, func(row *spoken) { row.predicates = append(slices.Clone(row.predicates), predicates...) })
}

func PredicatesOf(language contract.Language) []Predicate {
	sorted := slices.Clone(spokenIn(language).predicates)
	slices.SortFunc(sorted, func(a, b Predicate) int { return strings.Compare(a.Name, b.Name) })

	return sorted
}

func PredicateOf(language contract.Language, name string) (Predicate, bool) {
	predicates := PredicatesOf(language)

	at := slices.IndexFunc(predicates, func(predicate Predicate) bool { return predicate.Name == name })
	if at < 0 {
		return Predicate{}, false
	}

	return predicates[at], true
}
