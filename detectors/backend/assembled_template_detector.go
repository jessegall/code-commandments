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

func (AssembledTemplateDetector) Sin() sins.Sin { return backendsins.AssembledTemplate{} }

func (AssembledTemplateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_FuncCall").
		Where(engine.Match.AssemblesTemplate).
		Get()
}
