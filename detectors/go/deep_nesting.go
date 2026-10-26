// Package go holds the Go engine's detectors: each finds one sin and names it, and the sin names the skill that
// teaches the fix.
package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/sins"
	gosins "github.com/jessegall/code-commandments/sins/go"
)

// choicesAllowed is how many choices a construct may already sit inside; the one past it is flagged.
const choicesAllowed = 3

// DeepNestingDetector finds a choice buried inside three others within one Go function.
type DeepNestingDetector struct{}

func init() {
	detectors.Register(catalog.Go, DeepNestingDetector{})
}

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin {
	return gosins.DeepNesting{}
}

// Find is every branch or loop that opens a fourth level of choices inside its own function.
//
// A guard clause is never the one named, however deep it sits. `if err != nil { return err }` is the line Go
// asks for after every call that can fail and `if !ok { continue }` is how a loop says what it skips: neither
// buried anything, there is nothing inside them for a reader to come back to, and their authors cannot take
// them away, so told to flatten one they can only ignore the finding. Where the region around a guard is
// genuinely too deep, the construct that made it deep is named instead; where nothing else is, the choices a
// reader really holds open are within the rule and there is nothing to report.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return golang.In(codebase).
		WhereChoice().
		Where(engine.As(func(n golang.Node) bool { return n.ChoicesAbove() == choicesAllowed })).
		Reject(engine.Match.OnlyLeaves).
		Get()
}
