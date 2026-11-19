package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ConcreteCrossModuleDependencyDetector finds a constructor taking another module's service by its concrete class.
type ConcreteCrossModuleDependencyDetector struct{}

func init() { detectors.Register(catalog.PHP, ConcreteCrossModuleDependencyDetector{}) }

func (ConcreteCrossModuleDependencyDetector) Sin() sins.Sin {
	return backendsins.ConcreteCrossModuleDependency{}
}

func (ConcreteCrossModuleDependencyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Param").
		Where(engine.Match.InjectsAnotherModulesConcreteService).
		Reject(engine.Match.IsTest).
		Get()
}

func (ConcreteCrossModuleDependencyDetector) CrossFile() {}
