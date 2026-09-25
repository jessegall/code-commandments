package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// CoalescedLoopSubject is a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header.
type CoalescedLoopSubject struct{}

func init() {
	sins.Register(catalog.CSharp, CoalescedLoopSubject{})
}

// Definition is what the sin states about itself.
func (CoalescedLoopSubject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-coalesced-loop-subject",
		Skill:       skills.Flow{},
		Description: "a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header",
		Rule:        "Check for a missing collection at the top with an early return, so the loop runs over something that is there.",
		Suggestion:  "Write `if (items is null) { return; }` above the loop — or better, make the collection non-nullable where it comes from.",
	}
}
