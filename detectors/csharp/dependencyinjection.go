package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/dependencyinjection"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ContainerReachDetector finds a service located by a class that takes dependencies in its constructor.
type ContainerReachDetector struct{}

// OrphanedBindingDetector finds a registration without a production consumer.
type OrphanedBindingDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ContainerReachDetector{})
	detectors.Register(catalog.CSharp, OrphanedBindingDetector{})
}

func (ContainerReachDetector) Sin() sins.Sin { return cssins.ContainerReach{} }

func (ContainerReachDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Of(contract.CSharp).WhereCall().Where(dependencyinjection.ReachesContainer).Get()
}

func (OrphanedBindingDetector) Sin() sins.Sin { return cssins.OrphanedBinding{} }

func (OrphanedBindingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return dependencyinjection.OrphanedBindings(codebase.Of(contract.CSharp))
}

func (OrphanedBindingDetector) CrossFile() {}
