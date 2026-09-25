package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ErasedNullObjectDetector finds a null object that renders as ” filling a slot typed string, which erases it back into a blank string.
type ErasedNullObjectDetector struct{}

func init() { detectors.Register(catalog.Backend, ErasedNullObjectDetector{}) }

// Sin is the sin the detector finds.
func (ErasedNullObjectDetector) Sin() sins.Sin { return backendsins.ErasedNullObject{} }

// Find is every new of a class rendering as ” that fills a string-typed default or return.
func (ErasedNullObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Where(engine.As(func(n php.Node) bool { return php.RendersBlank(codebase, n.NewClassName()) })).
		Where(engine.As(func(n php.Node) bool { return n.FillsSlotTyped("string") })).
		Get()
}
