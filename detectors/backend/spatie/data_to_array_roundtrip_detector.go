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

func init() { detectors.Register(catalog.PHP, DataToArrayRoundtripDetector{}) }

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

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (DataToArrayRoundtripDetector) CrossFile() {}
