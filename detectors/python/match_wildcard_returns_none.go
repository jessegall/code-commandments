package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MatchWildcardReturnsNoneDetector finds a match over one enum's members whose wildcard alone answers absence.
type MatchWildcardReturnsNoneDetector struct{}

func init() {
	detectors.Register(catalog.Python, MatchWildcardReturnsNoneDetector{})
}

// Sin is the sin the detector finds.
func (MatchWildcardReturnsNoneDetector) Sin() sins.Sin {
	return pysins.MatchWildcardReturnsNone{}
}

// Find is every place the sin is committed.
func (MatchWildcardReturnsNoneDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Match").
		Where(engine.As(program.IsEnumMatchWithAbsentWildcard)).
		Get()
}
