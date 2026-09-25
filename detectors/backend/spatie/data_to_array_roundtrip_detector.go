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

// DataToArrayRoundtripDetector finds a Data object turned into an array only to be handed where the object itself would do.
type DataToArrayRoundtripDetector struct{}

func init() { detectors.Register(catalog.Backend, DataToArrayRoundtripDetector{}) }

// Sin is the sin the detector finds.
func (DataToArrayRoundtripDetector) Sin() sins.Sin { return backendsins.DataToArrayRoundtrip{} }

// Find is every redundant toArray() round trip.
func (DataToArrayRoundtripDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.MethodCallName() == "toArray" })).
		Where(engine.As(spatienode.Node.IsRedundantToArrayRoundtrip)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (DataToArrayRoundtripDetector) Repentable() {}
