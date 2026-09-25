package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// StringMatchMirrorsEnumDetector finds a match or switch over string literals that spell an enum's values.
type StringMatchMirrorsEnumDetector struct{}

func init() { detectors.Register(catalog.Backend, StringMatchMirrorsEnumDetector{}) }

// Sin is the sin the detector finds.
func (StringMatchMirrorsEnumDetector) Sin() sins.Sin { return backendsins.StringMatchMirrorsEnum{} }

// Find is every match or switch not on ->value whose case literals are one enum's values.
func (StringMatchMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return len(n.ArmConditionLiterals()) > 0 })).
		Reject(engine.As(php.Node.IsMatchOnEnumValue)).
		Where(engine.As(func(n php.Node) bool { return php.EnumsOf(codebase).MirroredBy(n.ArmConditionLiterals()) })).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (StringMatchMirrorsEnumDetector) WholeTree() {}
