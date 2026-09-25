package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// BlankStringOnTheWire is the blank-string-on-the-wire sin.
type BlankStringOnTheWire struct{}

func init() { sins.Register(catalog.Backend, BlankStringOnTheWire{}) }

// Definition is what the sin states about itself.
func (BlankStringOnTheWire) Definition() sins.Definition {
	return sins.Definition{
		Name:        "blank-string-on-the-wire",
		Skill:       skills.Absence{},
		Description: `A ` + "`" + `string` + "`" + ` field sent over the wire whose TypeScript reader has to check ` + "`" + `=== ''` + "`" + ` to mean "missing" — only that reader knows the blank stands for absence.`,
		Rule:        `A field that crosses the wire says absence in its TYPE; never ship a blank for the far side to decode as missing.`,
		Suggestion:  `` + "`" + `?string $x = null` + "`" + ` on the shape, and the reader asks ` + "`" + `x == null` + "`" + ` — one spelling of absence, agreed by both sides.`,
	}
}
