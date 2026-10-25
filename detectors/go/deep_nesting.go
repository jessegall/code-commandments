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

// Unpublished keeps the detector out of every catalog while it is calibrated: it is the first Go rule, and it
// has no fixture of its own until the Go fixture exists.
func (DeepNestingDetector) Unpublished() {}

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin {
	return gosins.DeepNesting{}
}

// Find is every branch or loop that opens a fourth level of choices inside its own function.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return golang.In(codebase).
		WhereChoice().
		Where(engine.As(func(n golang.Node) bool { return n.ChoicesAbove() == choicesAllowed })).
		Get()
}
