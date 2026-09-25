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

func init() { detectors.Register(catalog.Backend, InlineDocblockDetector{}) }

// Sin is the sin the detector finds.
func (InlineDocblockDetector) Sin() sins.Sin { return backendsins.InlineDocblock{} }

// Find is every node whose doc comment opens or closes on a content line.
func (InlineDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.HasDocComment)).
		Where(engine.As(php.Node.HasInlineDocblock)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (InlineDocblockDetector) Repentable() {}

// RunsLast says its fix reshapes code after every other fix has run.
func (InlineDocblockDetector) RunsLast() {}
