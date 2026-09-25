package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DictBagDetector finds a mapping parameter read by string keys, directly or through a helper handed the key: a record nobody declared.
type DictBagDetector struct{}

func init() {
	detectors.Register(catalog.Python, DictBagDetector{})
}

// Sin is the sin the detector finds.
func (DictBagDetector) Sin() sins.Sin {
	return pysins.DictBag{}
}

// Find is every place the sin is committed.
func (DictBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	direct := py.In(codebase).
		WhereExpression().
		Where(engine.As(py.Node.IsDictKeyRead)).
		Reject(engine.As(py.Node.IsWithinNamedConstructor)).
		Get()
	throughHelper := py.In(codebase).
		WhereCall().
		Where(engine.As(program.PassesLiteralKey)).
		Reject(engine.As(py.Node.IsWithinNamedConstructor)).
		Get()

	return append(direct, throughHelper...)
}
