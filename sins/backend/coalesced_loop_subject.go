package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CoalescedLoopSubject is the coalesced-loop-subject sin.
type CoalescedLoopSubject struct{}

func init() { sins.Register(catalog.Backend, CoalescedLoopSubject{}) }

// Definition is what the sin states about itself.
func (CoalescedLoopSubject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "coalesced-loop-subject",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `` + "`" + `foreach ($x[$k] ?? [] as …)` + "`" + ` — the absence check buried in the loop header instead of stated as a guard`,
		Rule:        `State an absent collection at the top as a guard (early return); don't bury ` + "`" + `?? []` + "`" + ` in a ` + "`" + `foreach` + "`" + ` header.`,
		Suggestion:  "An early `return` when the collection is absent, so the loop iterates something that is THERE.",
	}
}
