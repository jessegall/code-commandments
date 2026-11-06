package vue

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func HasDirective(name Name) engine.Check {
	return func(m engine.Match) bool { return Of(m).Has(name) }
}

func HasAnyDirective(names ...Name) engine.Check {
	return func(m engine.Match) bool { return Of(m).HasAny(names...) }
}

func IsTag(tags ...string) engine.Check {
	return func(m engine.Match) bool { return slices.Contains(tags, Of(m).Tag()) }
}
