package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// PlaceholderFilledDataDetector finds a dataclass built with an empty string in a required text field.
type PlaceholderFilledDataDetector struct{}

func init() {
	detectors.Register(catalog.Python, PlaceholderFilledDataDetector{})
}

func (PlaceholderFilledDataDetector) Sin() sins.Sin {
	return pysins.PlaceholderFilledData{}
}

func (PlaceholderFilledDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereCall().
		Where(engine.As(program.FillsRequiredTextWithBlank)).
		Reject(engine.Match.IsTest).
		Get()
}
