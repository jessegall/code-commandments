package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// BareStatePredicateDetector finds a method answering bool about its own state named as a narration rather than a question.
type BareStatePredicateDetector struct{}

func init() {
	detectors.Register(catalog.Python, BareStatePredicateDetector{})
}

// Sin is the sin the detector finds.
func (BareStatePredicateDetector) Sin() sins.Sin {
	return pysins.BareStatePredicate{}
}

// Find is every place the sin is committed.
func (BareStatePredicateDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsBareStatePredicate)).
		Get()
}
