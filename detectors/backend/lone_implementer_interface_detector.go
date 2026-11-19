package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// LoneImplementerInterfaceDetector finds an interface one class implements and nothing outside its module names.
type LoneImplementerInterfaceDetector struct{}

func init() { detectors.Register(catalog.PHP, LoneImplementerInterfaceDetector{}) }

func (LoneImplementerInterfaceDetector) Sin() sins.Sin { return backendsins.LoneImplementerInterface{} }

func (LoneImplementerInterfaceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Interface").
		Where(engine.Match.IsLoneAbstraction).
		Reject(engine.Match.IsTest).
		Get()
}

func (LoneImplementerInterfaceDetector) CrossFile() {}
