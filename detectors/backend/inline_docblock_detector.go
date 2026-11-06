package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// InlineDocblockDetector finds a docblock squeezed onto its content lines instead of opening and closing on lines of its own.
type InlineDocblockDetector struct{}

func init() { detectors.Register(catalog.PHP, InlineDocblockDetector{}) }

func (InlineDocblockDetector) Sin() sins.Sin { return backendsins.InlineDocblock{} }

func (InlineDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.HasDocComment)).
		Where(engine.As(php.Node.HasInlineDocblock)).
		Get()
}

func (InlineDocblockDetector) Repentable() {}

func (InlineDocblockDetector) RunsLast() {}
