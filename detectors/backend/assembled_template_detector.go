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

func init() { detectors.Register(catalog.PHP, AssembledTemplateDetector{}) }

const (
	minTemplateLines = 3
	minLiteralLines  = 2
)

func (AssembledTemplateDetector) Sin() sins.Sin { return backendsins.AssembledTemplate{} }

func (AssembledTemplateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("implode") })).
		Where(engine.As(func(n php.Node) bool { return n.Argument(0).IsNewlineSeparator() })).
		Where(engine.As(statesATemplate)).
		Get()
}

func statesATemplate(join php.Node) bool {
	lines := join.ArgumentArrayLiteral(1)

	return lines.Exists() && len(lines.ChildrenIn("items")) >= minTemplateLines && len(lines.LiteralItems()) >= minLiteralLines
}
