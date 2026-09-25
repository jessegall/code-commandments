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

// NamespaceCycleDetector finds two namespaces that reference each other, at the references of the thinner
// direction: the ones to cut.
type NamespaceCycleDetector struct{}

func init() { detectors.Register(catalog.Backend, NamespaceCycleDetector{}) }

// Sin is the sin the detector finds.
func (NamespaceCycleDetector) Sin() sins.Sin { return backendsins.NamespaceCycle{} }

// Exemptions lets a package declare the two-way associations its framework requires, which close no cycle.
func (NamespaceCycleDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.Association}}
}

// Find is one reference per class and class it names, imports aside, of the thinner direction of every mutual pair.
func (NamespaceCycleDetector) Find(codebase *engine.Codebase) []engine.Match {
	return namespaces.Distinct(namespaces.Of(codebase).ArrowsClosingAMutualPair())
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (NamespaceCycleDetector) CrossFile() {}
