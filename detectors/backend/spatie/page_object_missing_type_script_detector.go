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

// PageObjectMissingTypeScriptDetector finds a page object not marked for TypeScript.
type PageObjectMissingTypeScriptDetector struct{}

func init() { detectors.Register(catalog.Backend, PageObjectMissingTypeScriptDetector{}) }

// Sin is the sin the detector finds.
func (PageObjectMissingTypeScriptDetector) Sin() sins.Sin {
	return backendsins.PageObjectMissingTypeScript{}
}

// Find is every page object missing #[TypeScript].
func (PageObjectMissingTypeScriptDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.PageObjectMissingTypeScript)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (PageObjectMissingTypeScriptDetector) Repentable() {}
