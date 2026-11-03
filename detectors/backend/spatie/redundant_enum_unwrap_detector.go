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

// RedundantEnumUnwrapDetector finds an enum unwrapped to its value only to be hydrated back into a slot of its own type.
type RedundantEnumUnwrapDetector struct{}

func init() { detectors.Register(catalog.PHP, RedundantEnumUnwrapDetector{}) }

// Sin is the sin the detector finds.
func (RedundantEnumUnwrapDetector) Sin() sins.Sin { return backendsins.RedundantEnumUnwrap{} }

// Find is every ->value read that unwraps an enum into its own slot.
func (RedundantEnumUnwrapDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.IsPropertyFetchNamed("value") })).
		Where(engine.As(spatienode.Node.IsEnumUnwrapIntoItsOwnSlot)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (RedundantEnumUnwrapDetector) Repentable() {}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (RedundantEnumUnwrapDetector) CrossFile() {}
