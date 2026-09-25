package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CoalescedLoopSubjectDetector finds a foreach over a parameter's value defaulted to an empty array, which hides that the value may be missing.
type CoalescedLoopSubjectDetector struct{}

func init() { detectors.Register(catalog.Backend, CoalescedLoopSubjectDetector{}) }

// Sin is the sin the detector finds.
func (CoalescedLoopSubjectDetector) Sin() sins.Sin { return backendsins.CoalescedLoopSubject{} }

// Find is every loop subject that falls back to an empty array from a value reached through a parameter.
func (CoalescedLoopSubjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsLoopSubject)).
		Where(engine.As(php.Node.FallsBackToEmptyCollection)).
		Where(engine.As(func(n php.Node) bool { return n.FallbackSubject().ReachesIntoParameter() })).
		Get()
}
