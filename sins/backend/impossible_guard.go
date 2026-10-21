package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ImpossibleGuard is a comparison with null on a value whose own declared type has already ruled it out — either the type lies or the guard is noise.
type ImpossibleGuard struct{}

func init() {
	sins.Register(catalog.PHP, ImpossibleGuard{})
}

// Definition is what the sin states about itself.
func (ImpossibleGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "impossible-guard",
		Skill:       skills.TypeHonesty{},
		Description: "`if ($order === null)` where `$order` is typed `Order`, with no `?` — one of the two is wrong, and both are worth knowing",
		Rule:        "A guard answers a question the type leaves open: if the type says the value is there, delete the guard; if the value really can be missing, say so in the type.",
		Suggestion:  "Delete the check when the type is right. When callers really do pass null, type the parameter `?Order` and let the reader see it, then decide absence once where the value is born.",
	}
}
