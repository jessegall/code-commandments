package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// EnumValueMatchDetector finds a match on an enum's `.value` against the literals its members hold.
type EnumValueMatchDetector struct{}

func init() {
	detectors.Register(catalog.Python, EnumValueMatchDetector{})
}

func (EnumValueMatchDetector) Sin() sins.Sin {
	return pysins.EnumValueMatch{}
}

func (EnumValueMatchDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Match").
		Where(engine.As(program.IsMatchOnEnumValue)).
		Get()
}
