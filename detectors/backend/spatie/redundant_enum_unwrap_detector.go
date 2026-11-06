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

func (RedundantEnumUnwrapDetector) Sin() sins.Sin { return backendsins.RedundantEnumUnwrap{} }

func (RedundantEnumUnwrapDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.IsPropertyFetchNamed("value") })).
		Where(engine.As(spatienode.Node.IsEnumUnwrapIntoItsOwnSlot)).
		Get()
}

func (RedundantEnumUnwrapDetector) Repentable() {}

func (RedundantEnumUnwrapDetector) CrossFile() {}
