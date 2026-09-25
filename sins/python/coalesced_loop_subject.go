package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// CoalescedLoopSubject is `for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard.
type CoalescedLoopSubject struct{}

func init() {
	sins.Register(catalog.Python, CoalescedLoopSubject{})
}

// Definition is what the sin states about itself.
func (CoalescedLoopSubject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-coalesced-loop-subject",
		Skill:       skills.Flow{},
		Description: "`for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard",
		Rule:        "State an absent collection at the top as a guard; don't bury `or []` or `.get(k, [])` in a `for` header.",
		Suggestion:  "Return early when the collection is absent — or make the caller always hand one over — so the loop walks something that is there.",
	}
}
