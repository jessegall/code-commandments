package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// BloatedDocblockDetector finds a class whose docblock runs to more than one paragraph of prose.
type BloatedDocblockDetector struct{}

func init() { detectors.Register(catalog.Backend, BloatedDocblockDetector{}) }

// Sin is the sin the detector finds.
func (BloatedDocblockDetector) Sin() sins.Sin { return backendsins.BloatedDocblock{} }

// Find is every class whose doc comment holds two or more prose paragraphs.
func (BloatedDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.HasMultiParagraphDocblock)).
		Get()
}
