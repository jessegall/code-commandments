package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// PositionalTupleReturn is `return net, vat, currency` — a bundle of different things the caller must unpack by position, where a reordering breaks silently.
type PositionalTupleReturn struct{}

func init() {
	sins.Register(catalog.Python, PositionalTupleReturn{})
}

// Definition is what the sin states about itself.
func (PositionalTupleReturn) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-positional-tuple-return",
		Skill:       skills.ValueObjects{},
		Description: "`return net, vat, currency` — a bundle of different things the caller must unpack by position, where a reordering breaks silently",
		Rule:        "Return a named result — a frozen dataclass or a NamedTuple — not a tuple of different things the caller unpacks by position.",
		Suggestion:  "A small `@dataclass(frozen=True)` (or `NamedTuple`) whose fields name each slot.",
	}
}
