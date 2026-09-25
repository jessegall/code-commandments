package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ArrayBag is the array-bag sin.
type ArrayBag struct{}

func init() { sins.Register(catalog.Backend, ArrayBag{}) }

// Definition is what the sin states about itself.
func (ArrayBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "array-bag",
		Skill:       skills.ValueObjects{},
		Description: `String-indexing (` + "`" + `$arr['key']` + "`" + `) a structured array param instead of giving it a name — the type was never defined.`,
		Rule:        `Give a structured array a typed value object — never read a named field by string key off an ` + "`" + `array` + "`" + ` param.`,
		Suggestion:  "A Spatie `Data` object built via `::from($array)`.",
	}
}
