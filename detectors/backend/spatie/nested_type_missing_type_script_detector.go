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

// NestedTypeMissingTypeScriptDetector finds a field of a TypeScript Data class holding a nested wire type that is not marked for TypeScript.
type NestedTypeMissingTypeScriptDetector struct{}

func init() { detectors.Register(catalog.Backend, NestedTypeMissingTypeScriptDetector{}) }

// Sin is the sin the detector finds.
func (NestedTypeMissingTypeScriptDetector) Sin() sins.Sin {
	return backendsins.NestedTypeMissingTypeScript{}
}

// Find is every field whose nested wire type misses #[TypeScript].
func (NestedTypeMissingTypeScriptDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(engine.As(spatienode.Node.NestedWireTypeMissingTypeScript)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (NestedTypeMissingTypeScriptDetector) Repentable() {}
