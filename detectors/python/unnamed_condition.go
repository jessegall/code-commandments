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

func (UnnamedConditionDetector) Sin() sins.Sin {
	return pysins.UnnamedCondition{}
}

func (UnnamedConditionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereIs(engine.Conjunction).
		Where(engine.TestsAnUnnamedConcept).
		Get()
}
