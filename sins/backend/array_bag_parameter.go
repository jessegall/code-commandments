package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ArrayBagParameter is the array-bag-parameter sin.
type ArrayBagParameter struct{}

func init() { sins.Register(catalog.PHP, ArrayBagParameter{}) }

func (ArrayBagParameter) Definition() sins.Definition {
	return sins.Definition{
		Name:        "array-bag-parameter",
		Skill:       skills.ValueObjects{},
		Description: `A parameter declared ` + "`" + `array` + "`" + ` that callers fill with string-keyed records (` + "`" + `['mode' => …, 'plan' => …]` + "`" + `): the method never defined the type its fields belong to.`,
		Rule:        `A method of your own takes values and objects, never an ` + "`" + `array` + "`" + ` of named fields — the definition is at fault, not the caller filling it.`,
		Suggestion:  "Type the parameter as a Spatie `Data` object or a final readonly value object, and have callers construct it.",
	}
}
