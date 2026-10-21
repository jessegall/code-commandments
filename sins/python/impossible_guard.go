package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ImpossibleGuard is a test for None on a value whose own annotation has already ruled it out — either the annotation lies or the guard is noise.
type ImpossibleGuard struct{}

func init() {
	sins.Register(catalog.Python, ImpossibleGuard{})
}

// Definition is what the sin states about itself.
func (ImpossibleGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-impossible-guard",
		Skill:       skills.TypeHonesty{},
		Description: "`if order is None` where `order` is annotated as a class with no `| None` — one of the two is wrong, and both are worth knowing",
		Rule:        "A guard answers a question the type leaves open: if the type says the value is there, delete the guard; if the value really can be missing, say so in the type.",
		Suggestion:  "Delete the check when the annotation is right. When callers really do pass None, annotate the parameter `T | None` and let the reader see it, then decide absence once where the value is born.",
	}
}
