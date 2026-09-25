package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CancelledCoalesceDetector finds a ?? whose empty fallback is then compared away: ($x ?? ”) === ”.
type CancelledCoalesceDetector struct{}

func init() { detectors.Register(catalog.Backend, CancelledCoalesceDetector{}) }

// Sin is the sin the detector finds.
func (CancelledCoalesceDetector) Sin() sins.Sin { return backendsins.CancelledCoalesce{} }

// Find is every ?? falling back to an empty literal other than [] and compared with that same literal.
func (CancelledCoalesceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsCoalesce)).
		Where(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsEmptyLiteral() })).
		Reject(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsEmptyArrayLiteral() })).
		Where(engine.As(php.Node.IsCancelledCoalesce)).
		Get()
}
