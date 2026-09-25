package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MessageStringRaiseDetector finds a raise of a generic exception built from a message string: the failure named in prose.
type MessageStringRaiseDetector struct{}

func init() {
	detectors.Register(catalog.Python, MessageStringRaiseDetector{})
}

// Sin is the sin the detector finds.
func (MessageStringRaiseDetector) Sin() sins.Sin {
	return pysins.MessageStringRaise{}
}

// Find is every place the sin is committed.
func (MessageStringRaiseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("Raise").
		Where(engine.As(py.Node.IsGenericWithMessage)).
		Get()
}
