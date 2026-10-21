package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RethrownUnchanged is an `except` whose whole body raises the failure it caught, unchanged — a clause that catches, adds nothing and hands the failure back on.
type RethrownUnchanged struct{}

func init() {
	sins.Register(catalog.Python, RethrownUnchanged{})
}

// Definition is what the sin states about itself.
func (RethrownUnchanged) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-rethrown-unchanged",
		Skill:       skills.Exceptions{},
		Description: "an `except` whose whole body is `raise` or `raise error` — the clause catches the failure, adds nothing to it and hands it back on, which is what writing no clause does",
		Rule:        "Catch a failure only to do something about it: handle it, add what the caller needs, or let it pass by writing no clause.",
		Suggestion:  "Delete the clause and let the failure travel, or give it work to do: write the context it carries, roll back, release what was held, or raise a failure of your own `from` it.",
	}
}
