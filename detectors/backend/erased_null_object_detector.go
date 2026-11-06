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

func init() { detectors.Register(catalog.PHP, ErasedNullObjectDetector{}) }

func (ErasedNullObjectDetector) Sin() sins.Sin { return backendsins.ErasedNullObject{} }

func (ErasedNullObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(func(n php.Node) bool { return php.RendersBlank(codebase, n.NewClassName()) })).
		Where(engine.As(func(n php.Node) bool { return n.FillsSlotTyped("string") })).
		Get()
}

func (ErasedNullObjectDetector) CrossFile() {}
