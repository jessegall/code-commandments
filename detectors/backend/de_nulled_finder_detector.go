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

// DeNulledFinderDetector finds a finder returning a nullable object that every caller immediately settles for null:
// the absence belongs decided inside it.
type DeNulledFinderDetector struct{}

func init() { detectors.Register(catalog.Backend, DeNulledFinderDetector{}) }

// travels is how many callers must settle the null before the finder should.
const travels = 2

// Sin is the sin the detector finds.
func (DeNulledFinderDetector) Sin() sins.Sin { return backendsins.DeNulledFinder{} }

// WholeTree says the verdict needs every caller, and callers live in other files.
func (DeNulledFinderDetector) WholeTree() {}

// Exemptions excuses a method whose signature the framework dictates.
func (DeNulledFinderDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}}}
}

// Find is every method returning a nullable object whose two or more callers all settle its null.
func (d DeNulledFinderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, codebase.
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.ReturnsNullableObject)).
		Where(func(n engine.Match) bool { return deNulledByEveryCallerAndTravels(codebase, n) }).
		Get())
}

func deNulledByEveryCallerAndTravels(codebase *engine.Codebase, finder engine.Match) bool {
	class, method := php.EnclosingClassName(finder), php.EnclosingFunctionName(finder)
	if class == "" || method == "" {
		return false
	}
	callers := php.IndexOf(codebase).CallersOf(class, method)
	deNulled := 0
	for _, caller := range callers {
		if (php.Node{Match: caller}).ResultIsDeNulled() {
			deNulled++
		}
	}

	return deNulled >= travels && deNulled == len(callers)
}
