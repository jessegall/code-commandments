package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// BloatedDocblockDetector finds a class docstring of two or more paragraphs of prose.
type BloatedDocblockDetector struct{}

func init() {
	detectors.Register(catalog.Python, BloatedDocblockDetector{})
}

// Sin is the sin the detector finds.
func (BloatedDocblockDetector) Sin() sins.Sin {
	return pysins.BloatedDocblock{}
}

// Find is every place the sin is committed.
func (BloatedDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereClass().
		Where(engine.As(py.Node.HasMultiParagraphDocstring)).
		Get()
}
