package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/namespaces"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NamespaceCycleDetector finds two namespaces that reference each other, at the references of the thinner direction: the ones to cut.
type NamespaceCycleDetector struct{}

func init() { detectors.Register(catalog.PHP, NamespaceCycleDetector{}) }

func (NamespaceCycleDetector) Sin() sins.Sin { return backendsins.NamespaceCycle{} }

func (NamespaceCycleDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.Association}}
}

func (NamespaceCycleDetector) Find(codebase *engine.Codebase) []engine.Match {
	return namespaces.Distinct(namespaces.Of(codebase).ArrowsClosingAMutualPair())
}

func (NamespaceCycleDetector) CrossFile() {}
