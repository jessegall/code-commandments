package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// StringMatchMirrorsEnumDetector finds a match against strings that all name one enum's members.
type StringMatchMirrorsEnumDetector struct{}

func init() {
	detectors.Register(catalog.Python, StringMatchMirrorsEnumDetector{})
}

// Sin is the sin the detector finds.
func (StringMatchMirrorsEnumDetector) Sin() sins.Sin {
	return pysins.StringMatchMirrorsEnum{}
}

// Find is every place the sin is committed.
func (StringMatchMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Match").
		Where(engine.As(program.IsStringMatchMirroringEnum)).
		Get()
}
