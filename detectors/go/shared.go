package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
	_ "github.com/jessegall/code-commandments/engine/golang"
)

// language is what Go states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.Go
}

var (
	MatchDefaultReturnsNullDetector       = shared.Bind[shared.MatchDefaultReturnsNull, language]()
	LoneImplementerInterfaceDetector      = shared.BindLoneInterfaces[language]()
	InterfaceOverlapDetector              = shared.BindInterfaceOverlaps[language]("/", true)
	ConcreteCrossModuleDependencyDetector = shared.Bind[shared.ConcreteCrossModuleDependency, language]()
	MiddleManDetector                     = shared.Bind[shared.MiddleMan, language]()
	HandRolledRegistryDetector            = shared.Bind[shared.HandRolledRegistry, language]()
	TrainWreckDetector                    = shared.Bind[shared.TrainWreck, language]()
	UnusedPrivateMemberDetector           = shared.Bind[shared.UnusedPrivateMember, language]()
	UnreadCollaboratorDetector            = shared.Bind[shared.UnreadCollaborator, language]()
	GuardBesideTransitionDetector         = shared.Bind[shared.GuardBesideTransition, language]()
	AssembledTemplateDetector             = shared.Bind[shared.AssembledTemplate, language]()
	NamespaceDependencyDetector           = shared.BindLayerDependency[language]()
	ArrayBagDetector                      = shared.Bind[shared.ArrayBag, language]()
	ArrayBagParameterDetector             = shared.Bind[shared.ArrayBagParameter, language]()
	ArrayReturnBagDetector                = shared.Bind[shared.ArrayReturnBag, language]()
	CoupledFieldsDetector                 = shared.Bind[shared.CoupledFields, language]()
	FeatureEnvyDetector                   = shared.Bind[shared.FeatureEnvy, language]()
	HandedOutCollectionDetector           = shared.Bind[shared.HandedOutCollection, language]()
	MutableValueObjectDetector            = shared.Bind[shared.MutableValueObject, language]()
	MutableStaticStateDetector            = shared.Bind[shared.MutableStaticState, language]()
	DataClumpDetector                     = shared.BindGrouped[shared.DataClump, language]()
	BareStatePredicateDetector            = shared.Bind[shared.BareStatePredicate, language]()
	NarratedCommandDetector               = shared.Bind[shared.NarratedCommand, language]()
	KeyedLookupEnvyDetector               = shared.Bind[shared.KeyedLookupEnvy, language]()
	ScratchStateRestoreDetector           = shared.Bind[shared.ScratchStateRestore, language]()
	ConvertedArgumentDetector             = shared.BindGrouped[shared.ConvertedArgument, language]()
	DerivedArgumentDetector               = shared.Bind[shared.DerivedArgument, language]()
	ComputedBooleanArgumentDetector       = shared.Bind[shared.ComputedBooleanArgument, language]()
	FlagArgumentDetector                  = shared.Bind[shared.FlagArgument, language]()
	FilledParameterDetector               = shared.Bind[shared.FilledParameter, language]()
	ParamResolvedFromParamDetector        = shared.Bind[shared.ParamResolvedFromParam, language]()
	UnusedParameterDetector               = shared.Bind[shared.UnusedParameter, language]()
	CapabilityProbeHubDetector            = shared.Bind[shared.CapabilityProbeHub, language]()
	PhantomNullableDetector               = shared.Bind[shared.PhantomNullable, language]()
	RawDecodedArrayReturnDetector         = shared.Bind[shared.RawDecodedArrayReturn, language]()
	SwallowCatchDetector                  = shared.Bind[shared.SwallowCatch, language]()
	WrappingWithoutCauseDetector          = shared.Bind[shared.WrappingWithoutCause, language]()
	MessageAtThrowDetector                = shared.Bind[shared.MessageAtThrow, language](shared.Unpublished)
	ArchaeologyCommentDetector            = shared.Bind[shared.ArchaeologyComment, language]()
	NegativeSpaceCommentDetector          = shared.Bind[shared.NegativeSpaceComment, language]()
	RestatedCommentDetector               = shared.Bind[shared.RestatedComment, language]()
	DanglingDocReferenceDetector          = shared.Bind[shared.DanglingDocReference, language]()
	BloatedDocblockDetector               = shared.Bind[shared.BloatedDocblock, language]()
	DivergentTwinDetector                 = shared.BindGrouped[shared.DivergentTwin, language]()
	DuplicatedMechanismDetector           = shared.BindGrouped[shared.DuplicatedMechanism, language]()
	DuplicateFunctionDetector             = shared.BindGrouped[shared.DuplicateFunction, language]()
	NearDuplicateFunctionDetector         = shared.BindGrouped[shared.NearDuplicateFunction, language]()
	UnnamedVocabularyLiteralDetector      = shared.Bind[shared.UnnamedVocabularyLiteral, language]()
	RepeatedGuardDetector                 = shared.BindGrouped[shared.RepeatedGuard, language]()
	UnnamedConditionDetector              = shared.Bind[shared.UnnamedCondition, language]()
	RepeatedFolderPathDetector            = shared.Bind[shared.RepeatedFolderPath, language]()
	RedundantElseDetector                 = shared.Bind[shared.RedundantElse, language]()
	NonCountingForDetector                = shared.Bind[shared.NonCountingFor, language]()
	DeepNestingDetector                   = shared.Bind[shared.DeepNesting, language]()
	ReassignedParameterDetector           = shared.Bind[shared.ReassignedParameter, language]()
	LoopInvertedGuardDetector             = shared.Bind[shared.LoopInvertedGuard, language]()
	SubjectLadderDetector                 = shared.Bind[shared.SubjectLadder, language]()
	DuplicatedBranchTailDetector          = shared.Bind[shared.DuplicatedBranchTail, language]()
	UnreachableStatementDetector          = shared.Bind[shared.UnreachableStatement, language]()
)
