package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NullableRegistryLookupDetector finds a keyed store's lookup that answers a miss with null instead of failing.
type NullableRegistryLookupDetector struct{}

func init() { detectors.Register(catalog.Backend, NullableRegistryLookupDetector{}) }

// Sin is the sin the detector finds.
func (NullableRegistryLookupDetector) Sin() sins.Sin { return backendsins.NullableRegistryLookup{} }

// Find is every returned `$this->items[$key] ?? null`, save in a method that answers to an ancestor's, or where the
// lookup is only the last resort of a first-match dispatch.
func (NullableRegistryLookupDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsCoalesce)).
		Where(engine.As(php.Node.IsReturnedValue)).
		Where(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsNull() })).
		Where(engine.As(func(n php.Node) bool { return n.CoalesceLeft().IsOwnedKeyedLookup() })).
		Reject(engine.As(php.Node.IsFallbackOfAFirstMatch)).
		Reject(engine.As(func(n php.Node) bool {
			return php.ProgramOf(codebase).OverridesMethod(php.EnclosingClassName(n.Match), php.EnclosingFunctionName(n.Match))
		})).
		Get()
}
