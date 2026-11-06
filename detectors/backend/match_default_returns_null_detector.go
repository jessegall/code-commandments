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

func init() { detectors.Register(catalog.PHP, MatchDefaultReturnsNullDetector{}) }

func (MatchDefaultReturnsNullDetector) Sin() sins.Sin { return backendsins.MatchDefaultReturnsNull{} }

func (MatchDefaultReturnsNullDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsMatchWithAbsenceDefault)).
		Reject(engine.As(php.Node.MatchHandledArmsAdmitNull)).
		Get()
}
