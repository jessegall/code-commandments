package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// BareScopeEscapeDetector finds a query that drops every global scope at once instead of naming the one it steps out of.
type BareScopeEscapeDetector struct{}

func init() { detectors.Register(catalog.PHP, BareScopeEscapeDetector{}) }

func (BareScopeEscapeDetector) Sin() sins.Sin { return backendsins.BareScopeEscape{} }

func (BareScopeEscapeDetector) CrossFile() {}

func (BareScopeEscapeDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(laravelnode.Node.DropsEveryGlobalScope)).
		Reject(engine.Match.IsTest).
		Get()
}
