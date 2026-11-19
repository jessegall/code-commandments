package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
)

// language is what Python states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.Python
}

var (
	RedundantElseDetector         = shared.Bind[shared.RedundantElse, language]()
	LoopInvertedGuardDetector     = shared.Bind[shared.LoopInvertedGuard, language]()
	SubjectLadderDetector         = shared.Bind[shared.SubjectLadder, language]()
	DuplicatedBranchTailDetector  = shared.Bind[shared.DuplicatedBranchTail, language]()
	UnreachableStatementDetector  = shared.Bind[shared.UnreachableStatement, language]()
	NestedTernaryDetector         = shared.Bind[shared.NestedTernary, language]()
	TernaryStatementDetector      = shared.Bind[shared.TernaryStatement, language]()
	ShortCircuitStatementDetector = shared.Bind[shared.ShortCircuitStatement, language]()
)
