package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

// Grammar is how a language holds what every language has in a place of its own: where its tree files a call's arguments or a type's parents, which declarations the language itself calls, what its test files are named.
type Grammar struct {
	InterfaceSignatures func(Match) []string
	InterfaceVisible    func(target, from Match) bool
	UsesContract        func(implementation, contract Match) bool
	OwnsRecordBehavior  func(Match) bool
	ReactiveMember      func(Match) bool
	PublishedContracts  func(Match, []string) []string
	Arguments           func(Match) []Match
	Members             func(Match) []Match
	Parameters          func(Match) []Match
	Extends             func(Match) []Match
	Implements          func(Match) []Match
	WritesAttribute     func(Match, string) bool
	DeclaredName        func(Match) string
	Traits              func(Match) []Match
	ClassOf             func(Match) string
	ElementOf           func(Match) *contract.Type

	CodeWords func(Match) []string

	Documentable func(Match) bool

	TypeOf              func(Match) string
	Annotations         func(Match) []Match
	TypeKind            func(Match) string
	ReturnType          func(Match) Match
	Constructs          func(Match) Match
	Callers             func(Match) []Match
	TestFile            func(judged, path string) bool
	TestAssertion       func(Match) bool
	Implicit            func(Match) bool
	NamespaceOf         func(symbol string) string
	NamespaceSeparator  string
	QualifiedNameKey    func(string) string
	IsConstructor       func(Match) bool
	NamedConstructor    func(Match) bool
	ValueParameters     func(Match) []string
	HoldsPublicState    func(Match) bool
	ReceivesContext     func(Match, Match) bool
	PreservesPointer    func(Match) bool
	ValidatesRecord     func(Match) bool
	DeclaredClass       func(Match) string
	IsRecord            func(Match) bool
	IsValueClass        func(Match) bool
	Types               func(*Codebase) TypeSystem
	ListedClasses       func(Match) []string
	DeclaresMethods     func(Match) bool
	NamespacesNaming    func(Match) []string
	AnnotationNames     func(name string) []string
	Inherited           func(Match) bool
	Labels              func(Match) bool
	Continues           func(Match) bool
	NestedChoice        func(Match) bool
	NestingLimit        int
	Body                func(Match) []Match
	Otherwise           func(Match) []Match
	HasAlternative      func(Match) bool
	BindsItsOwnTest     func(Match) bool
	Condition           func(Match) Match
	RenderCondition     func(Match) Match
	ComparisonSubject   func(Match) Match
	Receiver            func(Match) Match
	Declares            func(Match) bool
	Computes            func(Match) bool
	ElementName         func(Match) string
	Binds               func(Match) bool
	JumpTarget          func(Match) bool
	Steps               func(Match) []Match
	Fallback            func(Match) Match
	StableFallback      func(Match) bool
	Grouping            func(Match) bool
	Thrown              func(Match) Match
	FailureLocation     func(Match) Match
	Cause               func(Match) Match
	Inert               func(Match) bool
	EmptyCollection     func(Match) bool
	ConstructedType     func(Match) string
	GenericFailures     []string
	GenericMessage      bool
	TakesFailureMessage func(Match) bool
	MessageParts        func(Match) []Match
	Hashing             HashRules
	SpelledFolder       FolderSpelling
	TypeTest            func(Match) bool
	Guard               func(Match) Guard
	TwinPairs           func(*Codebase) TwinPairs
	MechanismFacts      func(*Codebase) MechanismFacts
	VocabularyWords     func(Match) []string
	UnnamedVocabulary   func(*Codebase) []Match
	CopyBody            func(Match) []Match
	CopyExclusions      func(Match) CopyExclusions
	MethodOwner         func(Match) string
	Keywords            map[string]prose.Spelled
	ErrorHandler        func(Match) Match
	Decode              func(Match) bool
	Encode              func(Match) bool
	DecodedDestination  func(Match) Match
	UntypedReturn       func(Match) bool
	MaskedInvariant     func(Match) bool
	DefaultValue        func(Match) Match
	CallableTypes       []string
	NullableCallback    func(Match) bool
	CallbackTarget      func(Match) Match
	KeyedStore          func(Match) Match
	NullableLookup      func(Match) bool
	NullableFields      func(Match) []Match
	PhantomFields       func(Match) []Match
	AbsenceTrace        func(Match) []string
	CapabilityTest      func(branch, condition Match) CapabilityTest
	CapabilityFallback  func(Match) bool
	SelfReceiver        func(Match) bool
	Iterated            func(Match) Match
	ConditionalArms     func(Match) (Match, Match)
	Spreads             func(Match) bool
	BlankDefault        func(Match) bool
	BlankReplacement    func(Match) (Match, Match)
	DeNulledFinder      func(Match) bool
	ManufacturesFill    func(Match) bool
	PlaceholderData     func(Match) bool
	CoalescedLoop       func(Match) bool
	ConditionalSpread   func(Match) bool
	NullForgiving       func(Match) bool
	LiteralCollection   func(Match) bool
	EnumValues          func(Match) []string
	EnumCatalog         func(*Codebase) Enums
	EnumChainOwner      func(Match) string
	EnumCase            func(Match) (string, string)
	EnumMembership      func(Match) []string
	EnumArms            func(Match) []string
	TypeDispatch        func(Match) bool
	DispatchTest        func(Match) (Match, string)
	ConstClass          func(Match) bool
	EnumTable           func(Match) bool
	SideTables          func(*Codebase) []Match
	CallConversion      func(Match) CallConversion
	ScalarTypes         []string
	BooleanTypes        []string
	ArgumentLookup      func(Match) (Match, Match)
	RegistryFields      func(Match) []string
	KeyedWrite          func(Match) Match
	VariableKey         func(Match) bool
	MissingKeys         func(Match) []Match
	ReturnedValue       func(Match) Match
	InitializedValue    func(Match) Match
	AssignmentSides     func(Match) (Match, Match)
	RecordKeys          func(Match) []string
	RecordReturnOwner   func(Match) Match
	LooseRecordType     func(Match) bool
	ReactiveRecord      func(Match) bool
	StaticStorage       func(Match) bool
	ImportModule        func(Match) string
	ModuleKey           func(Match) string
	ModuleLayer         func(Match) string
	SameModule          func(Match, Match) bool
	MemberOrder         func(Match) int
	JoinedLines         func(Match) []Match
	TupleSlots          func(Match) []Match
	ExecutesQuery       func(Match) bool
	PrivateMember       func(Match) bool
	Getter              func(Match) bool
	Collaborator        func(Match) bool
	DynamicMembers      func(Match) bool
	MemberLookup        func(Match) (string, func(string) bool)
	ArrowReturn         func(Match) bool
	NullDefault         func(Match) bool
	FlagArgument        func(Match) bool
	UnusedParameter     func(Match) bool
	OwnsSignature       func(Match) bool
	FilledParameter     func(Match) bool
	ComputedArguments   func(Match) bool
	ResolvedParameter   func(Match) bool
	DerivedArguments    func(*Codebase) []Match
}

var TypeKinds = []string{"class", "interface", "enum", "trait", "record", "struct", "protocol"}

func ReadAs(language contract.Language, read Grammar) {
	registers(language, func(row *spoken) { row.grammar = &read })
}

func InFields(fields ...string) func(Match) []Match {
	return func(match Match) []Match {
		var filling []Match
		for _, child := range match.Children() {
			for _, field := range fields {
				if child.node.InRole() == field {
					filling = append(filling, child)
				}
			}
		}

		return filling
	}
}

func InField(field string) func(Match) Match {
	return func(match Match) Match {
		return match.Child(field)
	}
}

func InAnyField(fields ...string) func(Match) Match {
	return func(match Match) Match {
		for _, child := range match.Children() {
			for _, field := range fields {
				if child.node.InRole() == field {
					return child
				}
			}
		}

		return Match{}
	}
}

func OfKind(kind, field string) func(Match) Match {
	return func(match Match) Match {
		if match.Kind() != kind {
			return Match{}
		}

		return match.Child(field)
	}
}

func KindIn(kinds ...string) func(Match) bool {
	return func(match Match) bool {
		return slices.Contains(kinds, match.Kind())
	}
}

func Kinds(words map[string]string) func(Match) string {
	return func(match Match) string {
		return words[match.Kind()]
	}
}

func (m Match) grammar() Grammar {
	if m.file == nil {
		return Grammar{}
	}

	return grammarOf(m.file.Language())
}

func grammarOf(language contract.Language) Grammar {
	read := spokenIn(language).grammar
	if read == nil {
		return Grammar{}
	}

	return *read
}

func (m Match) listed(read func(Match) []Match) []Match {
	if read == nil || m.node == nil {
		return nil
	}

	return read(m)
}
