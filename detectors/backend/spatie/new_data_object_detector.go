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

// NewDataObjectDetector finds a rich Data object built with new, where ::from() belongs.
type NewDataObjectDetector struct{}

func init() { detectors.Register(catalog.Backend, NewDataObjectDetector{}) }

// Sin is the sin the detector finds.
func (NewDataObjectDetector) Sin() sins.Sin { return backendsins.NewDataObject{} }

// Find is every new of a rich Data class, save a parameter default and a Data handed values already built.
func (NewDataObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(spatienode.Node.IsNewData)).
		Reject(engine.As(php.Node.IsParameterDefault)).
		Reject(engine.As(spatienode.Node.IsHandedConstructedData)).
		Where(engine.As(spatienode.Node.IsRichData)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (NewDataObjectDetector) Repentable() {}
