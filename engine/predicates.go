package engine

import (
	"slices"

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
