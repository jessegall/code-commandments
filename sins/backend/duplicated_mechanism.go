package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DuplicatedMechanism is the duplicated-mechanism sin.
type DuplicatedMechanism struct{}

func init() { sins.Register(catalog.Backend, DuplicatedMechanism{}) }

// Unpublished keeps the sin out of every catalog while its detector is calibrated.
func (DuplicatedMechanism) Unpublished() {}

// Definition is what the sin states about itself.
func (DuplicatedMechanism) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicated-mechanism",
		Skill:       skills.FixAtTheSource{},
		Description: `Two or more classes in different files assemble the SAME rare set of collaborators — the same mechanism written twice in different words, so the decision behind it is made differently in each`,
		Rule:        `Before writing a mechanism, search for the concept, not the exact name you had in mind. If it already exists twice, merge the two copies into one class every caller can use, instead of adding a third.`,
		Suggestion:  "Extract the shared mechanism into one class the callers depend on, and delete the copies.",
	}
}
