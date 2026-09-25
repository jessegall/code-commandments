package vue

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// HasDirective keeps the elements carrying a directive of the name.
func HasDirective(name Name) engine.Check {
	return func(m engine.Match) bool { return Of(m).Has(name) }
}

// HasAnyDirective keeps the elements carrying any of the directives.
func HasAnyDirective(names ...Name) engine.Check {
	return func(m engine.Match) bool { return Of(m).HasAny(names...) }
}

// IsTag keeps the elements whose tag is one of these.
func IsTag(tags ...string) engine.Check {
	return func(m engine.Match) bool { return slices.Contains(tags, Of(m).Tag()) }
}
