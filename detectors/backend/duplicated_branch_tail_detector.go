package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DuplicatedBranchTailDetector finds the duplicated-branch-tail sin.
type DuplicatedBranchTailDetector struct{}

func init() { detectors.Register(catalog.PHP, DuplicatedBranchTailDetector{}) }

func (DuplicatedBranchTailDetector) Sin() sins.Sin { return backendsins.DuplicatedBranchTail{} }

func (DuplicatedBranchTailDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_If").
		Where(engine.As(php.Node.EndsEveryBranchAlike)).
		Reject(engine.Match.IsTest).
		Get()
}
