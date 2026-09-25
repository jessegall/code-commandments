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

// AllNullableDataDetector finds a Data class whose every field is nullable: nothing about it is certain.
type AllNullableDataDetector struct{}

func init() { detectors.Register(catalog.Backend, AllNullableDataDetector{}) }

// Sin is the sin the detector finds.
func (AllNullableDataDetector) Sin() sins.Sin { return backendsins.AllNullableData{} }

// Find is every Data class whose promoted fields are all nullable.
func (AllNullableDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(php.Node.EveryConstructorParamNullable)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (AllNullableDataDetector) Repentable() {}

// RequiresBestDesign says a report that the finding is wrong must name the cleanest design for the class.
func (AllNullableDataDetector) RequiresBestDesign() {}
