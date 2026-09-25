package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// WrappingWithoutCauseDetector finds a catch that throws a new exception and drops the one it caught.
type WrappingWithoutCauseDetector struct{}

func init() { detectors.Register(catalog.Backend, WrappingWithoutCauseDetector{}) }

// Sin is the sin the detector finds.
func (WrappingWithoutCauseDetector) Sin() sins.Sin { return backendsins.WrappingWithoutCause{} }

// Find is every exception thrown inside a catch without the caught one as its cause.
func (WrappingWithoutCauseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(php.Node.IsRethrowWithoutCause)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (WrappingWithoutCauseDetector) Repentable() {}
