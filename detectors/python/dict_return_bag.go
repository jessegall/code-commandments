package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DictReturnBagDetector finds a def returning a dict display of named fields: a record handed back without a type.
type DictReturnBagDetector struct{}

func init() {
	detectors.Register(catalog.Python, DictReturnBagDetector{})
}

// Sin is the sin the detector finds.
func (DictReturnBagDetector) Sin() sins.Sin {
	return pysins.DictReturnBag{}
}

// Find is every place the sin is committed.
func (DictReturnBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Dict").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(hasTwoStringKeys)).
		Where(engine.As(py.Node.IsReturnedValue)).
		Where(engine.As(py.Node.HasFieldNameKeys)).
		Reject(engine.As(py.Node.SpreadsAnother)).
		Reject(engine.As(py.Node.HasNestedCollectionValue)).
		Reject(engine.As(py.Node.IsJSONSchema)).
		Reject(engine.As(py.Node.IsMemberTable)).
		Reject(engine.As(py.Node.IsProjection)).
		Reject(engine.As(program.IsInTypedDictFunction)).
		Reject(engine.As(program.IsInContractMethod)).
		Get()
}

// hasTwoStringKeys says whether a dict display writes two or more string keys.
func hasTwoStringKeys(n py.Node) bool {
	return n.StringKeyCount() >= 2
}
