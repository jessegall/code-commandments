package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnnamedCondition is a condition of three or more parts asking something of two or more objects — a concept the code never named.
type UnnamedCondition struct{}

func init() {
	sins.Register(catalog.Backend, UnnamedCondition{})
}

// Unpublished keeps the sin out of every catalog while its detector is calibrated against real code.
func (UnnamedCondition) Unpublished() {}

// Definition is what the sin states about itself.
func (UnnamedCondition) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unnamed-condition",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "`if ($order->isPaid() && $customer->trusted && $slot->open)` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it",
		Rule:        "A condition that takes a reader more than a glance is a concept: give it a name and ask for it by that name.",
		Suggestion:  "Move the condition onto the class that owns most of it, as a method or a property whose name says what it decides — `if ($order->readyToShip($slot))` — and let each object answer for its own part.",
	}
}
