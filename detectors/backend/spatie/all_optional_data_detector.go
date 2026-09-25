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

// AllOptionalDataDetector finds a Data class whose every field is Optional: nothing about it is certain.
type AllOptionalDataDetector struct{}

func init() { detectors.Register(catalog.Backend, AllOptionalDataDetector{}) }

// Sin is the sin the detector finds.
func (AllOptionalDataDetector) Sin() sins.Sin { return backendsins.AllOptionalData{} }

// Find is every Data class whose promoted fields are all Optional.
func (AllOptionalDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(spatienode.Node.EveryConstructorParamOptional)).
		Get()
}
