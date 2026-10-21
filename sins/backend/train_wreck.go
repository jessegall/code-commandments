package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// TrainWreck is a chain of sends crossing three or more of the project's own types — the caller depends on the shape of objects it never asked for.
type TrainWreck struct{}

func init() {
	sins.Register(catalog.Backend, TrainWreck{})
}


// Definition is what the sin states about itself.
func (TrainWreck) Definition() sins.Definition {
	return sins.Definition{
		Name:        "train-wreck",
		Skill:       skills.TellDontAsk{},
		Description: "`$order->customer()->address()->city()` — a chain crossing three of the project's own types, so a change three objects away reaches this caller",
		Rule:        "Ask the object you have for the answer you want; never walk through its neighbours to find it.",
		Suggestion:  "Give the object you hold a method that answers the question, so the walk happens where the objects live: `$order->city()`, with each type asking only its own neighbour.",
	}
}
