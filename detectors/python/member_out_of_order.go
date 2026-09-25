package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MemberOutOfOrderDetector finds a constant declared below a field of its class.
type MemberOutOfOrderDetector struct{}

func init() {
	detectors.Register(catalog.Python, MemberOutOfOrderDetector{})
}

// Sin is the sin the detector finds.
func (MemberOutOfOrderDetector) Sin() sins.Sin {
	return pysins.MemberOutOfOrder{}
}

// Find is every place the sin is committed.
func (MemberOutOfOrderDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereStatement().
		Where(engine.As(program.IsConstantBelowField)).
		Get()
}
