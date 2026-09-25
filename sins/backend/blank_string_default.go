package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// BlankStringDefault is the blank-string-default sin.
type BlankStringDefault struct{}

func init() { sins.Register(catalog.Backend, BlankStringDefault{}) }

// Definition is what the sin states about itself.
func (BlankStringDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "blank-string-default",
		Skill:       skills.Absence{},
		Description: "`string $x = ''` standing in for absence — then asked `$x === ''`",
		Rule:        `Model a value that may not be there in the type; never default a total ` + "`" + `string` + "`" + ` to ` + "`" + `''` + "`" + ` and read that blank back as "missing".`,
		Suggestion:  `Say it in the type — ` + "`" + `?string $x = null` + "`" + `, or an ` + "`" + `Option<string>` + "`" + ` — so the blank is not a value the reader has to decode.`,
	}
}
