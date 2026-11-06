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

func init() { detectors.Register(catalog.PHP, WrappingWithoutCauseDetector{}) }

func (WrappingWithoutCauseDetector) Sin() sins.Sin { return backendsins.WrappingWithoutCause{} }

func (WrappingWithoutCauseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(php.Node.IsRethrowWithoutCause)).
		Get()
}

func (WrappingWithoutCauseDetector) Repentable() {}
