package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// CeremonyDocblockDetector finds a def docstring that says nothing but what the signature already says.
type CeremonyDocblockDetector struct{}

func init() {
	detectors.Register(catalog.Python, CeremonyDocblockDetector{})
}

// Sin is the sin the detector finds.
func (CeremonyDocblockDetector) Sin() sins.Sin {
	return pysins.CeremonyDocblock{}
}

// Find is every place the sin is committed.
func (CeremonyDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(py.Node.HasCeremonyDocstring)).
		Get()
}
