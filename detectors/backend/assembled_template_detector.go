package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// AssembledTemplateDetector finds a multi-line string built by joining a list of line fragments with newlines.
type AssembledTemplateDetector struct{}

func init() { detectors.Register(catalog.Backend, AssembledTemplateDetector{}) }

const (
	// minTemplateLines is how many lines a joined list needs to be a template.
	minTemplateLines = 3
	// minLiteralLines is how many of them must be written out as strings.
	minLiteralLines = 2
)

// Sin is the sin the detector finds.
func (AssembledTemplateDetector) Sin() sins.Sin { return backendsins.AssembledTemplate{} }

// Find is every implode with a newline separator over an array literal of three or more lines, two written out,
// passed there or assigned to the variable passed.
func (AssembledTemplateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("implode") })).
		Where(engine.As(func(n php.Node) bool { return n.Argument(0).IsNewlineSeparator() })).
		Where(engine.As(statesATemplate)).
		Get()
}

func statesATemplate(join php.Node) bool {
	lines := join.ArgumentArrayLiteral(1)

	return lines.Exists() && len(lines.ChildrenIn("items")) >= minTemplateLines && len(lines.LiteralItems()) >= minLiteralLines
}
