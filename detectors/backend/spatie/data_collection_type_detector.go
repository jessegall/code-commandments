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

// DataCollectionTypeDetector finds a field typed as the legacy DataCollection, where a typed array or collection belongs.
type DataCollectionTypeDetector struct{}

func init() { detectors.Register(catalog.Backend, DataCollectionTypeDetector{}) }

// Sin is the sin the detector finds.
func (DataCollectionTypeDetector) Sin() sins.Sin { return backendsins.DataCollectionType{} }

// Find is every field typed as a DataCollection.
func (DataCollectionTypeDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(engine.As(spatienode.Node.PropertyTypedAsDataCollection)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (DataCollectionTypeDetector) Repentable() {}
