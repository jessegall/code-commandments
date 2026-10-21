package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// QueryInLoop is a query run inside a loop — one per turn, so the cost grows with the data while the code reads as one call.
type QueryInLoop struct{}

func init() {
	sins.Register(catalog.Backend, QueryInLoop{})
}

// Definition is what the sin states about itself.
func (QueryInLoop) Definition() sins.Definition {
	return sins.Definition{
		Name:        "query-in-loop",
		Skill:       skills.LaravelIdioms{},
		Description: "a query run inside a loop — one query per turn, so the work grows with the data while the code reads as though it were one call",
		Rule:        "Ask the database once for everything the loop needs, before the loop.",
		Suggestion:  "Load what the loop needs in one query before it: eager-load the relation with `with()`, or fetch the rows by their keys and index them, then let the loop read what is already in memory.",
	}
}
