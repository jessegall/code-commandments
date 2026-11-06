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

func init() { detectors.Register(catalog.PHP, AllNullableDataDetector{}) }

func (AllNullableDataDetector) Sin() sins.Sin { return backendsins.AllNullableData{} }

func (AllNullableDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(php.Node.EveryConstructorParamNullable)).
		Get()
}

func (AllNullableDataDetector) Repentable() {}

func (AllNullableDataDetector) RequiresBestDesign() {}
