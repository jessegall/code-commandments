package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RethrownUnchanged is a `catch` whose whole body throws the exception it caught, unchanged — a clause that catches, adds nothing and hands the failure back on.
type RethrownUnchanged struct{}

func init() {
	sins.Register(catalog.Backend, RethrownUnchanged{})
}

// Definition is what the sin states about itself.
func (RethrownUnchanged) Definition() sins.Definition {
	return sins.Definition{
		Name:        "rethrown-unchanged",
		Skill:       skills.Exceptions{},
		Description: "a `catch` whose whole body is `throw $e;` — the clause catches the failure, adds nothing to it and hands it back on, which is what writing no clause does",
		Rule:        "Catch a failure only to do something about it: handle it, add what the caller needs, or let it pass by writing no clause.",
		Suggestion:  "Delete the clause and let the failure travel, or give it work to do: write the context it carries, roll back, release what was held, or throw a failure of your own with it as the previous exception.",
	}
}
