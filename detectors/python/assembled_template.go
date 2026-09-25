package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// templateLines is how many joined lines make a template.
const templateLines = 3

// templateFixedLines is how many of them must be text written out.
const templateFixedLines = 2

// AssembledTemplateDetector finds a multi-line string assembled by joining a list of lines with a newline.
type AssembledTemplateDetector struct{}

func init() {
	detectors.Register(catalog.Python, AssembledTemplateDetector{})
}

// Sin is the sin the detector finds.
func (AssembledTemplateDetector) Sin() sins.Sin {
	return pysins.AssembledTemplate{}
}

// Find is every place the sin is committed.
func (AssembledTemplateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsNewlineJoin)).
		Where(engine.As(func(n py.Node) bool { return len(n.JoinedLines()) >= templateLines })).
		Where(engine.As(func(n py.Node) bool { return n.FixedLineCount() >= templateFixedLines })).
		Get()
}
