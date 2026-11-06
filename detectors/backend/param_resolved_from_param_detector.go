package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ParamResolvedFromParamDetector finds a method taking a container and a key to look up in it, where it wants the looked-up object itself.
type ParamResolvedFromParamDetector struct{}

func init() { detectors.Register(catalog.PHP, ParamResolvedFromParamDetector{}) }

func (ParamResolvedFromParamDetector) Sin() sins.Sin { return backendsins.ParamResolvedFromParam{} }

func (ParamResolvedFromParamDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.Boundary}}
}

func (ParamResolvedFromParamDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Reject(func(n engine.Match) bool { return takesABoundary(codebase, n) }).
		Where(engine.As(php.Node.UnpacksTargetFromContainerParam)).
		Get()
}

func takesABoundary(codebase *engine.Codebase, method engine.Match) bool {
	for _, param := range php.Params(method) {
		written := param.Node().Declared
		if written != nil && written.Kind == "named" && packages.Excuses(codebase, packages.Boundary, written.Name, "") {
			return true
		}
	}

	return false
}

func (ParamResolvedFromParamDetector) CrossFile() {}
