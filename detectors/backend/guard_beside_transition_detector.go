package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// GuardBesideTransitionDetector finds a state check that throws, sitting in front of the call that changes that state.
type GuardBesideTransitionDetector struct{}

func init() { detectors.Register(catalog.PHP, GuardBesideTransitionDetector{}) }

func (GuardBesideTransitionDetector) Sin() sins.Sin { return backendsins.GuardBesideTransition{} }

func (GuardBesideTransitionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_If").
		Where(engine.As(php.Node.GuardsAheadOfItsTransition)).
		Reject(engine.Match.IsTest).
		Get()
}

func (GuardBesideTransitionDetector) CrossFile() {}
