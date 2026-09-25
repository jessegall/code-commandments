package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MemberAfterMethodDetector finds state declared in a class body below a method, reading none of the methods above it.
type MemberAfterMethodDetector struct{}

func init() {
	detectors.Register(catalog.Python, MemberAfterMethodDetector{})
}

// Sin is the sin the detector finds.
func (MemberAfterMethodDetector) Sin() sins.Sin {
	return pysins.MemberAfterMethod{}
}

// Find is every place the sin is committed.
func (MemberAfterMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereStatement().
		Where(engine.As(program.IsMemberAfterMethod)).
		Get()
}
