package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"slices"
)

// TypeSwitchDetector finds an isinstance switch over classes the program declares: behaviour each type should answer itself.
type TypeSwitchDetector struct{}

func init() {
	detectors.Register(catalog.Python, TypeSwitchDetector{})
}

// Sin is the sin the detector finds.
func (TypeSwitchDetector) Sin() sins.Sin {
	return pysins.TypeSwitch{}
}

// Find is every place the sin is committed.
func (TypeSwitchDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsTypeSwitchHead)).
		Where(engine.As(func(n py.Node) bool {
			return !slices.ContainsFunc(n.TypeSwitchClasses(), func(class string) bool { return !program.DeclaresClass(class) })
		})).
		Reject(engine.As(py.Node.IsInDunder)).
		Reject(engine.As(py.Node.IsWithinNamedConstructor)).
		Reject(engine.As(py.Node.TypeSwitchTranslatesEveryArm)).
		Get()
}
