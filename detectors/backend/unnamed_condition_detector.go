package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UnnamedConditionDetector finds a condition standing for a concept nobody named.
type UnnamedConditionDetector struct{}

func init() { detectors.Register(catalog.Backend, UnnamedConditionDetector{}) }

// Sin is the sin the detector finds.
func (UnnamedConditionDetector) Sin() sins.Sin { return backendsins.UnnamedCondition{} }

// Unpublished keeps the rule out of every catalog while it is calibrated against real code.
func (UnnamedConditionDetector) Unpublished() {}

// Find is every logical chain a branch decides by that asks something of two or more objects.
func (UnnamedConditionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereIs(engine.Conjunction).
		Where(engine.TestsAnUnnamedConcept).
		Get()
}
