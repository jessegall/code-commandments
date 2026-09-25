package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// BlankStringDefault is `x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope.
type BlankStringDefault struct{}

func init() {
	sins.Register(catalog.Python, BlankStringDefault{})
}

// Definition is what the sin states about itself.
func (BlankStringDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-blank-string-default",
		Skill:       skills.Absence{},
		Description: "`x: str = \"\"` standing in for absence — then asked `x == \"\"`, `not x` or `if x:` in its own scope",
		Rule:        "Say a value may be missing in its type; never default a `str` to `\"\"` and read that blank back as \"missing\".",
		Suggestion:  "`x: str | None = None`, asked `x is None` — so the blank is not a value every reader has to decode.",
	}
}
