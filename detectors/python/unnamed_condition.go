package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// UnnamedConditionDetector finds a condition standing for a concept nobody named.
type UnnamedConditionDetector struct{}

func init() {
	detectors.Register(catalog.Python, UnnamedConditionDetector{})
}

// Sin is the sin the detector finds.
func (UnnamedConditionDetector) Sin() sins.Sin {
	return pysins.UnnamedCondition{}
}

// Find is every logical chain a branch decides by that asks something of two or more objects.
func (UnnamedConditionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereIs(engine.Conjunction).
		Where(engine.TestsAnUnnamedConcept).
		Get()
}
