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

func init() { detectors.Register(catalog.PHP, InlineThrowDetector{}) }

func (InlineThrowDetector) Sin() sins.Sin { return backendsins.InlineThrow{} }

func (InlineThrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsThrow() })).
		Where(engine.As(func(n php.Node) bool { return n.IsCallArgument() || n.IsCallReceiver() })).
		Get()
}
