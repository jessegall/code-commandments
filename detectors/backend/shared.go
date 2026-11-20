package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
)

// language is what PHP states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.PHP
}

var (
	ConcreteCrossModuleDependencyDetector = shared.Bind[shared.ConcreteCrossModuleDependency, language]()
	UndeclaredMemberDetector              = shared.Bind[shared.UndeclaredMember, language](shared.Excusing(usesDynamicMembers, packages.Exemption{Tag: packages.DynamicMembers}))
	LoneImplementerInterfaceDetector      = shared.Bind[shared.LoneImplementerInterface, language]()
	MatchDefaultReturnsNullDetector       = shared.Bind[shared.MatchDefaultReturnsNull, language]()
	RedundantArrowReturnTypeDetector      = shared.Bind[shared.RedundantArrowReturnType, language](shared.Repentable)
	ConvertedArgumentDetector             = shared.BindGrouped[shared.ConvertedArgument, language]()
	TypeSwitchDetector                    = shared.Bind[shared.TypeSwitch, language]()
	ConstClassEnumDetector                = shared.Bind[shared.ConstClassEnum, language]()
	SideTableTraitDetector                = shared.Bind[shared.SideTableTrait, language]()
	EnumCaseOrChainDetector               = shared.Bind[shared.EnumCaseOrChain, language]()
	InArrayMirrorsEnumDetector            = shared.Bind[shared.InArrayMirrorsEnum, language]()
	StringMatchMirrorsEnumDetector        = shared.Bind[shared.StringMatchMirrorsEnum, language]()
	BlankStringDefaultDetector            = shared.Bind[shared.BlankStringDefault, language]()
	DeNulledFinderDetector                = shared.Bind[shared.DeNulledFinder, language](shared.Excusing(func(_ *engine.Codebase, match engine.Match) bool { return isContractMethod(match) }, packages.Exemption{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}}))
	ManufacturedFakeFillDetector          = shared.Bind[shared.ManufacturedFakeFill, language]()
	CoalescedLoopSubjectDetector          = shared.Bind[shared.CoalescedLoopSubject, language]()
	ConditionalArraySpreadDetector        = shared.Bind[shared.ConditionalArraySpread, language]()
	CapabilityProbeHubDetector            = shared.Bind[shared.CapabilityProbeHub, language]()
	PhantomNullableDetector               = shared.BindTraced[shared.PhantomNullable, language]()
	NullableRegistryLookupDetector        = shared.Bind[shared.NullableRegistryLookup, language]()
	MaskedInvariantDetector               = shared.Bind[shared.MaskedInvariant, language]()
	NullableCallbackDetector              = shared.Bind[shared.NullableCallback, language]()
	RawDecodedArrayReturnDetector         = shared.Bind[shared.RawDecodedArrayReturn, language]()
	ArchaeologyCommentDetector            = shared.Bind[shared.ArchaeologyComment, language]()
	NegativeSpaceCommentDetector          = shared.Bind[shared.NegativeSpaceComment, language]()
	RestatedCommentDetector               = shared.Bind[shared.RestatedComment, language]()
	DanglingDocReferenceDetector          = shared.Bind[shared.DanglingDocReference, language]()
	BloatedDocblockDetector               = shared.Bind[shared.BloatedDocblock, language]()
	CeremonyDocblockDetector              = shared.Bind[shared.CeremonyDocblock, language]()
	StackedDocblockDetector               = shared.BindLast[shared.StackedDocblock, language](shared.Repentable)
	DuplicateFunctionDetector             = shared.BindGrouped[shared.DuplicateFunction, language]()
	NearDuplicateFunctionDetector         = shared.BindGrouped[shared.NearDuplicateFunction, language](shared.Rejecting(isContractMethod, packages.Exemption{Tag: packages.ContractMethod}))
	UnnamedVocabularyLiteralDetector      = shared.Bind[shared.UnnamedVocabularyLiteral, language]()
	DuplicatedMechanismDetector           = shared.BindGrouped[shared.DuplicatedMechanism, language]()
	DivergentTwinDetector                 = shared.BindGrouped[shared.DivergentTwin, language]()
	RepeatedGuardDetector                 = shared.BindGrouped[shared.RepeatedGuard, language]()
	RepeatedTypeGuardDetector             = shared.BindGrouped[shared.RepeatedTypeGuard, language]()
	ListMirrorsFolderDetector             = shared.Bind[shared.ListMirrorsFolder, language]()
	RepeatedFolderPathDetector            = shared.Bind[shared.RepeatedFolderPath, language]()
	UnnamedConditionDetector              = shared.Bind[shared.UnnamedCondition, language]()
	RedundantElseDetector                 = shared.Bind[shared.RedundantElse, language](shared.Repentable)
	CancelledCoalesceDetector             = shared.Bind[shared.CancelledCoalesce, language]()
	NonCountingForDetector                = shared.Bind[shared.NonCountingFor, language]()
	DeepNestingDetector                   = shared.Bind[shared.DeepNesting, language]()
	ReassignedParameterDetector           = shared.Bind[shared.ReassignedParameter, language]()
	LoopInvertedGuardDetector             = shared.Bind[shared.LoopInvertedGuard, language](shared.Repentable)
	DuplicatedBranchTailDetector          = shared.Bind[shared.DuplicatedBranchTail, language]()
	UnreachableStatementDetector          = shared.Bind[shared.UnreachableStatement, language]()
	NestedTernaryDetector                 = shared.Bind[shared.NestedTernary, language](shared.Repentable)
	TernaryStatementDetector              = shared.Bind[shared.TernaryStatement, language](shared.Repentable)
	ShortCircuitStatementDetector         = shared.Bind[shared.ShortCircuitStatement, language](shared.Repentable)
	SwallowCatchDetector                  = shared.Bind[shared.SwallowCatch, language](shared.Excusing(catchesOnlyControlSignals, packages.Exemption{Tag: packages.ControlSignal}))
	RethrownUnchangedDetector             = shared.Bind[shared.RethrownUnchanged, language]()
	WrappingWithoutCauseDetector          = shared.Bind[shared.WrappingWithoutCause, language](shared.Repentable, shared.Excusing(buildsValidationResponse, packages.Exemption{Tag: packages.ValidationResponse}))
	GenericExceptionDetector              = shared.Bind[shared.GenericException, language]()
	MessageAtThrowDetector                = shared.Bind[shared.MessageAtThrow, language]()
)

func usesDynamicMembers(codebase *engine.Codebase, access engine.Match) bool {
	return packages.Excuses(codebase, packages.DynamicMembers, access.Receiver().TypeName(), "")
}

func catchesOnlyControlSignals(codebase *engine.Codebase, catch engine.Match) bool {
	types := php.Node{Match: catch}.CaughtTypes()
	for _, caught := range types {
		if !packages.Excuses(codebase, packages.ControlSignal, caught, "") {
			return false
		}
	}

	return len(types) > 0
}

func isContractMethod(match engine.Match) bool {
	return packages.Excuses(match.Codebase(), packages.ContractMethod, php.EnclosingClassName(match), php.EnclosingFunctionName(match))
}

func buildsValidationResponse(codebase *engine.Codebase, thrown engine.Match) bool {
	return thrown.Kind() == "Expr_StaticCall" && packages.Excuses(codebase, packages.ValidationResponse,
		strings.TrimLeft(thrown.Child("class").Name(), `\`), thrown.Child("name").Name())
}
