package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// InLiteralsMirrorsEnumDetector finds an `in` test among string literals that all name one enum's members.
type InLiteralsMirrorsEnumDetector struct{}

func init() {
	detectors.Register(catalog.Python, InLiteralsMirrorsEnumDetector{})
}

// Sin is the sin the detector finds.
func (InLiteralsMirrorsEnumDetector) Sin() sins.Sin {
	return pysins.InLiteralsMirrorsEnum{}
}

// Find is every place the sin is committed.
func (InLiteralsMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Compare").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(func(n py.Node) bool { return program.EnumsHoldAll(n.MembershipLiteralKeys()) })).
		Get()
}
