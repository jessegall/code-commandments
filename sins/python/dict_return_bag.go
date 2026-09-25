package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DictReturnBag is `return {"total": …, "tax": …}` — a record of several fields handed back as a dict its callers read by string key.
type DictReturnBag struct{}

func init() {
	sins.Register(catalog.Python, DictReturnBag{})
}

// Definition is what the sin states about itself.
func (DictReturnBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-dict-return-bag",
		Skill:       skills.ValueObjects{},
		Description: "`return {\"total\": …, \"tax\": …}` — a record of several fields handed back as a dict its callers read by string key",
		Rule:        "Return a typed value — a frozen dataclass — not a dict of several named fields.",
		Suggestion:  "A `@dataclass(frozen=True)` for the result, built where it is returned — or a `TypedDict` when a dict must cross a boundary.",
	}
}
