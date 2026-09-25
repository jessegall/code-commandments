package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MessageAtThrowDetector finds an exception handed its message at the throw instead of by a named factory.
type MessageAtThrowDetector struct{}

func init() { detectors.Register(catalog.Backend, MessageAtThrowDetector{}) }

// Sin is the sin the detector finds.
func (MessageAtThrowDetector) Sin() sins.Sin { return backendsins.MessageAtThrow{} }

// Find is every thrown new that passes a message string.
func (MessageAtThrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereNew().
		Where(engine.As(php.Node.IsThrownWithMessage)).
		Get()
}
