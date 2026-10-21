package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// BlockedAwait is `.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back.
type BlockedAwait struct{}

func init() {
	sins.Register(catalog.CSharp, BlockedAwait{})
}

// Definition is what the sin states about itself.
func (BlockedAwait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-blocked-await",
		Skill:       skills.FixAtTheSource{},
		Description: "`.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else",
		Rule:        "Work in flight is awaited, never blocked on: `await` it, and make the caller async too, however far up that goes.",
		Suggestion:  "Write `await` and mark the method `async`, then follow the callers: the breadth of that change IS the problem surfacing, and stopping it with `.Result` at one boundary is what buries the deadlock until load finds it.",
	}
}
