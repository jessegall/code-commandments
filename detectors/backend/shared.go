package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
)

// language is what PHP states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.PHP
}

var (
	RedundantElseDetector         = shared.Bind[shared.RedundantElse, language](shared.Repentable)
	LoopInvertedGuardDetector     = shared.Bind[shared.LoopInvertedGuard, language](shared.Repentable)
	DuplicatedBranchTailDetector  = shared.Bind[shared.DuplicatedBranchTail, language]()
	UnreachableStatementDetector  = shared.Bind[shared.UnreachableStatement, language]()
	NestedTernaryDetector         = shared.Bind[shared.NestedTernary, language](shared.Repentable)
	TernaryStatementDetector      = shared.Bind[shared.TernaryStatement, language](shared.Repentable)
	ShortCircuitStatementDetector = shared.Bind[shared.ShortCircuitStatement, language](shared.Repentable)
)
