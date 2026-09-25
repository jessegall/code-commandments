package concurrent

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	concurrentskills "github.com/jessegall/code-commandments/skill/backend/concurrent"
)

// ConcurrentSubclass is the concurrent-subclass sin.
type ConcurrentSubclass struct{}

func init() { sins.Register(catalog.Backend, ConcurrentSubclass{}) }

// Definition is what the sin states about itself.
func (ConcurrentSubclass) Definition() sins.Definition {
	return sins.Definition{
		Name:        "concurrent-subclass",
		Skill:       concurrentskills.ConcurrentState{},
		Description: "Class `extends Concurrent` instead of composing `Concurrent<self>`",
		Rule:        "Compose `Concurrent<self>` via a `::for()` factory; never `extends Concurrent`.",
		Suggestion:  "Compose `Concurrent<self>` behind a `::for($id)` factory.",
		Requires:    requiresConcurrent,
	}
}
