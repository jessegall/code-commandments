package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MaskedInvariant is a literal answering for the object's own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way.
type MaskedInvariant struct{}

func init() {
	sins.Register(catalog.Python, MaskedInvariant{})
}

// Definition is what the sin states about itself.
func (MaskedInvariant) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-masked-invariant",
		Skill:       skills.TypeHonesty{},
		Description: "a literal answering for the object's own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way",
		Rule:        "Make the invariant certain instead of masking it: pass the per-call value as a parameter, or hold it non-optional from construction.",
		Suggestion:  "Hand the value to the methods that need it (`covers(period, day)`) or build a per-call object holding it, and delete the fallback.",
	}
}
