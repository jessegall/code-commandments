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

// InjectedServiceNotHiddenDetector finds a page object exposing an injected service on the wire, where #[Hidden] belongs.
type InjectedServiceNotHiddenDetector struct{}

func init() { detectors.Register(catalog.Backend, InjectedServiceNotHiddenDetector{}) }

// Sin is the sin the detector finds.
func (InjectedServiceNotHiddenDetector) Sin() sins.Sin { return backendsins.InjectedServiceNotHidden{} }

// Find is every page object with an injected service not hidden.
func (InjectedServiceNotHiddenDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsPageObject)).
		Where(engine.As(spatienode.Node.HasUnhiddenInjectedService)).
		Get()
}
