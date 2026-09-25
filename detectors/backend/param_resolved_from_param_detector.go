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

// ParamResolvedFromParamDetector finds a method taking a container and a key to look up in it, where it wants the
// looked-up object itself.
type ParamResolvedFromParamDetector struct{}

func init() { detectors.Register(catalog.Backend, ParamResolvedFromParamDetector{}) }

// Sin is the sin the detector finds.
func (ParamResolvedFromParamDetector) Sin() sins.Sin { return backendsins.ParamResolvedFromParam{} }

// Exemptions lets a framework entry point unpack its input: a method taking a request is a boundary.
func (ParamResolvedFromParamDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.Boundary}}
}

// Find is every method, boundaries aside, that resolves a key parameter on an object parameter it otherwise only
// reads properties of.
func (ParamResolvedFromParamDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Reject(func(n engine.Match) bool { return takesABoundary(codebase, n) }).
		Where(engine.As(php.Node.UnpacksTargetFromContainerParam)).
		Get()
}

// takesABoundary says whether the method takes a parameter of a type a package declares a boundary.
func takesABoundary(codebase *engine.Codebase, method engine.Match) bool {
	for _, param := range php.Params(method) {
		written := param.Node().Declared
		if written != nil && written.Kind == "named" && packages.Excuses(codebase, packages.Boundary, written.Name, "") {
			return true
		}
	}

	return false
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (ParamResolvedFromParamDetector) CrossFile() {}
