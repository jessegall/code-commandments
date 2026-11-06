// Package go holds the Go engine's detectors: each finds one sin and names it, and the sin names the skill that teaches the fix.
package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/sins"
	gosins "github.com/jessegall/code-commandments/sins/go"
)

const choicesAllowed = 3

// DeepNestingDetector finds a choice buried inside three others within one Go function.
type DeepNestingDetector struct{}

func init() {
	detectors.Register(catalog.Go, DeepNestingDetector{})
}

func (DeepNestingDetector) Sin() sins.Sin {
	return gosins.DeepNesting{}
}

func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return golang.In(codebase).
		WhereChoice().
		Where(engine.As(func(n golang.Node) bool { return n.ChoicesAbove() == choicesAllowed })).
		Reject(engine.Match.OnlyLeaves).
		Get()
}
