package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DeNulledFinder is a def answering `X | None` that every caller turns into a failure anyway — the annotation says the value may be missing and nobody agrees.
type DeNulledFinder struct{}

func init() {
	sins.Register(catalog.Python, DeNulledFinder{})
}

// Definition is what the sin states about itself.
func (DeNulledFinder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-de-nulled-finder",
		Skill:       skills.Absence{},
		Description: "a def annotated `X | None` whose every caller, two or more of them, turns the None into a failure where it stands — asserting it, raising on it, or reading straight through it",
		Rule:        "A finder decides what a missing thing means once, where it looks: if every caller insists the value is there, say so in the signature and raise where it is not.",
		Suggestion:  "Return `X` and raise a named exception when there is nothing to answer with — `raise OrderNotFound(order_id)` — and let the callers read it plainly. Keep the `| None` only where a caller really does have something else to do about the absence, and then let it do that instead of asserting.",
	}
}
