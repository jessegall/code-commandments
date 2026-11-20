package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// BlankStringOnTheWire is the blank-string-on-the-wire sin.
type BlankStringOnTheWire struct{}

func init() { sins.Register(catalog.PHP, BlankStringOnTheWire{}) }

func (BlankStringOnTheWire) Definition() sins.Definition {
	return sins.Definition{
		Name:        "blank-string-on-the-wire",
		Skill:       skills.Absence{},
		Description: "A string sent over the wire that the frontend reads as absent when it is blank.",
		Rule:        `A field that crosses the wire says absence in its TYPE; never ship a blank for the far side to decode as missing.`,
		Suggestion:  `` + "`" + `?string $x = null` + "`" + ` on the shape, and the reader asks ` + "`" + `x == null` + "`" + ` — one spelling of absence, agreed by both sides.`,
	}
}
