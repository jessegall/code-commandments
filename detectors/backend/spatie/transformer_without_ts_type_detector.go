package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// TransformerWithoutTsTypeDetector finds a Data field given a transformer but no TypeScript type for what it writes.
type TransformerWithoutTsTypeDetector struct{}

func init() { detectors.Register(catalog.Backend, TransformerWithoutTsTypeDetector{}) }

// Sin is the sin the detector finds.
func (TransformerWithoutTsTypeDetector) Sin() sins.Sin { return backendsins.TransformerWithoutTsType{} }

// Find is every WithTransformer on a Data class whose transformer lacks a TypeScript type.
func (TransformerWithoutTsTypeDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.IsAttributeNamed("WithTransformer") })).
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(spatienode.Node.TransformerLacksTsType)).
		Get()
}
