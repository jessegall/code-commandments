package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
)

// language is what C# states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.CSharp
}

var (
	RedundantElseDetector        = shared.Bind[shared.RedundantElse, language]()
	LoopInvertedGuardDetector    = shared.Bind[shared.LoopInvertedGuard, language]()
	SubjectLadderDetector        = shared.Bind[shared.SubjectLadder, language]()
	DuplicatedBranchTailDetector = shared.Bind[shared.DuplicatedBranchTail, language]()
	UnreachableStatementDetector = shared.Bind[shared.UnreachableStatement, language]()
	NestedTernaryDetector        = shared.Bind[shared.NestedTernary, language]()
)
