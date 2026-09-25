package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// TernaryStatementDetector finds a ternary used as a statement to pick which side effect runs.
type TernaryStatementDetector struct{}

func init() { detectors.Register(catalog.Backend, TernaryStatementDetector{}) }

// Sin is the sin the detector finds.
func (TernaryStatementDetector) Sin() sins.Sin { return backendsins.TernaryStatement{} }

// Find is every ternary whose value is thrown away.
func (TernaryStatementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsTernary)).
		Where(engine.As(php.Node.ResultIsDiscarded)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (TernaryStatementDetector) Repentable() {}
