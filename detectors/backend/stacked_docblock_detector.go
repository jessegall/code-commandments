package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// StackedDocblockDetector finds two or more docblocks stacked on one declaration.
type StackedDocblockDetector struct{}

func init() { detectors.Register(catalog.Backend, StackedDocblockDetector{}) }

// Sin is the sin the detector finds.
func (StackedDocblockDetector) Sin() sins.Sin { return backendsins.StackedDocblock{} }

// Find is every node with more than one doc comment attached.
func (StackedDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.HasDocComment)).
		Where(engine.As(php.Node.HasStackedDocblocks)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (StackedDocblockDetector) Repentable() {}

// RunsLast says its fix reshapes code after every other fix has run.
func (StackedDocblockDetector) RunsLast() {}
