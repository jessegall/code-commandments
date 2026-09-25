package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// InlineThrowDetector finds a throw buried in a ?? that feeds a call, where a guard clause belongs.
type InlineThrowDetector struct{}

func init() { detectors.Register(catalog.Backend, InlineThrowDetector{}) }

// Sin is the sin the detector finds.
func (InlineThrowDetector) Sin() sins.Sin { return backendsins.InlineThrow{} }

// Find is every ?? that throws as its fallback and is passed to a call or sent a method.
func (InlineThrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsThrow() })).
		Where(engine.As(func(n php.Node) bool { return n.IsCallArgument() || n.IsCallReceiver() })).
		Get()
}
