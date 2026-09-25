package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MatchDefaultReturnsNullDetector finds a match whose default arm hands back null, false or [] instead of failing on an unhandled case.
type MatchDefaultReturnsNullDetector struct{}

func init() { detectors.Register(catalog.Backend, MatchDefaultReturnsNullDetector{}) }

// Sin is the sin the detector finds.
func (MatchDefaultReturnsNullDetector) Sin() sins.Sin { return backendsins.MatchDefaultReturnsNull{} }

// Find is every match with an absence default, unless its handled arms already return null from their own declarations.
func (MatchDefaultReturnsNullDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsMatchWithAbsenceDefault)).
		Reject(engine.As(php.Node.MatchHandledArmsAdmitNull)).
		Get()
}
