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

func init() { detectors.Register(catalog.PHP, StackedDocblockDetector{}) }

func (StackedDocblockDetector) Sin() sins.Sin { return backendsins.StackedDocblock{} }

func (StackedDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.HasDocComment)).
		Where(engine.As(php.Node.HasStackedDocblocks)).
		Get()
}

func (StackedDocblockDetector) Repentable() {}

func (StackedDocblockDetector) RunsLast() {}
