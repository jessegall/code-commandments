package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.PHP, engine.Grammar{
		Arguments:           values,
		Receiver:            engine.InAnyField("var", "class"),
		Binds:               binding,
		Members:             declaredMembers,
		InterfaceSignatures: interfaceSignatures,
		UsesContract:        usesContract,
		Parameters:          engine.InFields("params"),
		Extends:             engine.InFields("extends"),
		Implements:          engine.InFields("implements"),
		Traits:              traitNames,
		DeclaredName:        propertyName,
		ClassOf:             classOf,
		TypeOf:              typeOf,
		Annotations:         attributes,
		TypeKind:            engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:          engine.InField("returnType"),
		RecordKeys:          func(match engine.Match) []string { return (Node{Match: match}).StringKeys() },
		RecordReturnOwner:   recordReturnOwner,
		LooseRecordType:     looseRecordType,
		ParameterType:       engine.InField("type"),
		Constructs:          constructed,
		Callers:             callers,
		DocTags:             phpDocTags,
		CodeWords:           func(node engine.Match) []string { return Node{Match: node}.CodeWords() },
		ElementOf:           func(node engine.Match) *contract.Type { return TypesOf(node.Codebase()).ElementOf(node) },
		TestFile:            testFile,
		Implicit:            magic,
		Labels:              func(name engine.Match) bool { return name.Parent().Kind() == "Arg" && name.Node().InRole() == "name" },
		NamedConstructor: func(method engine.Match) bool { return Node{Match: method}.IsNamedConstructor() },
		ValueParameters:  func(method engine.Match) []string { return Node{Match: method}.ValueParamSignature() },
		IsConstructor: func(method engine.Match) bool {
			return method.Kind() == "Stmt_ClassMethod" && method.Name() == "__construct"
		},
		HoldsPublicState: func(parameter engine.Match) bool {
			return parameter.HasModifier("public") && !HasDataInjection(parameter)
		},
		ReceivesContext:    receivesContext,
		SameModule:         sameModule,
		DeclaredClass:      func(parameter engine.Match) string { return Written(parameter.Node().Written).Class() },
		IsRecord:           func(class engine.Match) bool { return ProgramOf(class.Codebase()).ClassIsRecord(class.Identity()) },
		IsValueClass:       isValueDependency,
		ListedClasses:      listedProjectClasses,
		PublishedContracts: publishedContracts,
		Types:              func(codebase *engine.Codebase) engine.TypeSystem { return ProgramOf(codebase) },
		MemberLookup:       memberLookup,
		NamespacesNaming: func(contract engine.Match) []string {
			return TypeReferencesOf(contract.Codebase()).NamespacesNaming(contract.Identity())
		},
		DeclaresMethods: func(declaration engine.Match) bool {
			return slices.ContainsFunc(declaration.ChildrenIn("stmts"), func(member engine.Match) bool { return member.Kind() == "Stmt_ClassMethod" })
		},
		NamespaceSeparator: `\`,
		QualifiedNameKey:   func(name string) string { return strings.ToLower(strings.TrimLeft(name, `\`)) },
		NamespaceOf: func(symbol string) string {
			class, _, _ := strings.Cut(symbol, "::")

			return engine.Before(strings.TrimSuffix(class, "()"), `\`)
		},
		Continues:         engine.KindIn("Stmt_ElseIf"),
		NestedChoice:      engine.KindIn("Stmt_If"),
		NestingLimit:      2,
		Steps:             engine.InFields("loop"),
		Thrown:            engine.OfKind("Expr_Throw", "expr"),
		FailureLocation:   engine.Match.Thrown,
		MessageParts:      messageParts,
		ExpectedTestCatch: expectsTestFailure,
		SwallowedExit: func(statement engine.Match) bool {
			return statement.Is(engine.Return) && statement.Child("expr").Exists()
		},
		EmptyCollection: func(held engine.Match) bool { return Node{Match: held}.IsEmptyArrayLiteral() },
		ConstructedType: func(held engine.Match) string { return strings.TrimLeft(Node{Match: held}.ConstructedClass(), `\`) },
		GenericFailures: genericFailures,
		Hashing:         Hashing,
		CallConversion:  callConversion,
		ArrowReturn: func(match engine.Match) bool {
			node := Node{Match: match}
			return match.Kind() == "Expr_ArrowFunction" && node.HasReturnType() && node.ReturnTypeRestatesItsExpression()
		},
		NullDefault: func(match engine.Match) bool {
			node := Node{Match: match}
			return node.IsMatchWithAbsenceDefault() && !node.MatchHandledArmsAdmitNull()
		},
		ConstClass:   func(m engine.Match) bool { n := Node{Match: m}; return n.IsScalarConstClass() && !n.ExtendsAClass() },
		TypeDispatch: typeDispatch,
		EnumTable:    engine.As(Node.ListsSomeOfItsCasesInAConstant),
		EnumValues:   enumValues,
		EnumChainOwner: func(match engine.Match) string {
			owner := (Node{Match: match}).OrChainComparedClass()
			if owner != "" && EnumsOf(match.Codebase()).IsIndexed(owner) {
				return owner
			}
			return ""
		},
		EnumMembership: func(match engine.Match) []string {
			n := Node{Match: match}
			if n.CallsFunction("in_array") {
				return n.ArgumentArrayLiterals(1)
			}
			return nil
		},
		EnumArms: func(match engine.Match) []string {
			n := Node{Match: match}
			if n.IsMatchOnEnumValue() {
				return nil
			}
			return n.ArmConditionLiterals()
		},
		BlankDefault:      blankDefault,
		BlankReplacement:  blankReplacement,
		DeNulledFinder:    deNulledFinder,
		ManufacturesFill:  manufacturesFill,
		CoalescedLoop:     coalescedLoop,
		ConditionalSpread: engine.As(Node.IsConditionalArraySpread),
		CapabilityTest: func(_, condition engine.Match) engine.CapabilityTest {
			return engine.CapabilityTest{Subject: engine.OfKind("Expr_Instanceof", "expr")(condition)}
		},
		CapabilityFallback: func(node engine.Match) bool { return isBareValue(node) || node.Kind() == "Expr_Array" },
		ConditionalArms:    engine.ReadPair("if", "else"),
		PhantomFields:      phantomFields,
		AbsenceTrace:       absenceTrace,
		NullableLookup:     nullableLookup,
		MaskedInvariant:    engine.As(Node.MasksOwnState),
		NullableCallback:   engine.As(Node.HasNullNormalisedNullableCallback),
		Decode: func(call engine.Match) bool {
			node := Node{Match: call}
			return node.CallsFunction("json_decode") && !node.DecodesItsOwnEncoding() && !node.IsWithinInheritedMethod()
		},
		CommentReferences: commentReferences,
		CommentLocation:   engine.Commented.Documents,
		DocParagraphs:     func(comment engine.Commented) int { return DocParagraphs(comment.Text) },
		CeremonyDoc:       engine.As(Node.HasCeremonyDocblock),
		CopyExclusions:    copyExclusions,
		UnnamedVocabulary: unnamedVocabulary,
		TwinPairs: func(codebase *engine.Codebase) engine.TwinPairs {
			return engine.TwinPairs{Units: TwinUnits(codebase), Pairs: Divergences(codebase)}
		},
		Guard: guard,
		MechanismFacts: func(codebase *engine.Codebase) engine.MechanismFacts {
			reach := ReachOf(codebase)
			return engine.MechanismFacts{Classes: reach.Classes, IsType: reach.IsType, IsTerminal: reach.IsTerminal, Namespace: func(class engine.Match) string { return Node{Match: class}.NamespaceName() }}
		},
		SpelledFolder:     func(site engine.Match) (engine.SpelledFolder, bool) { return Node{Match: site}.SpelledFolder() },
		Body:              engine.InFields("stmts"),
		Otherwise:         otherwise,
		HasAlternative:    func(branch engine.Match) bool { return branch.Child("else").Exists() },
		Condition:         condition,
		ComparisonSubject: comparisonSubject,
		EnumCase:          enumCase,
		ArgumentLookup:    indexedRegistryRead,
		JoinedLines:       joinedLines,
		RegistryFields:    registryFields,
		KeyedWrite:        engine.OfKind("Expr_Assign", "var"),
		VariableKey:       func(key engine.Match) bool { return !isBareValue(key) },
		MissingKeys:       missingKeys,
		JumpTarget:        engine.KindIn("Stmt_Label"),
		ElementName:       elementName,
		Computes:          computes,
	})

}

func declaredMembers(declaration engine.Match) []engine.Match {
	declared := declaration.ChildrenIn("stmts")
	for _, parameter := range ConstructorParams(declaration) {
		if slices.Contains(parameter.Node().Modifiers, "promoted") {
			declared = append(declared, parameter)
		}
	}

	return declared
}

func binding(statement engine.Match) bool {
	if statement.Kind() != "Stmt_Expression" {
		return false
	}

	return statement.Child("expr").Is(engine.Assignment)
}

func testFile(path, _ string) bool {
	return strings.HasSuffix(path, "Test.php") || engine.InFolderNamed(path, "tests", "test")
}

func values(call engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range call.ChildrenIn("args") {
		if value := argument.Child("value"); value.Exists() {
			handed = append(handed, value)
		}
	}

	return handed
}

func constructed(construction engine.Match) engine.Match {
	if construction.Kind() != "Expr_New" {
		return engine.Match{}
	}

	return construction.Child("class")
}

func callers(function engine.Match) []engine.Match {
	switch function.Kind() {
	case "Stmt_Function":
		return IndexOf(function.Codebase()).FunctionCallersOf(function)
	case "Stmt_ClassMethod":
		if class := function.EnclosingType(); class.Node().Symbol != "" {
			return IndexOf(function.Codebase()).CallersOf(class.Node().Symbol, function.Name())
		}
	}

	return nil
}

func attributes(declaration engine.Match) []engine.Match {
	var carried []engine.Match
	for _, group := range declaration.ChildrenIn("attrGroups") {
		carried = append(carried, group.ChildrenIn("attrs")...)
	}

	return carried
}

func magic(declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_ClassMethod" && Node{Match: declaration}.IsMagicMethod()
}

func phpDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		for _, tag := range docTags(comment) {
			name, _, _ := strings.Cut(strings.TrimPrefix(tag, "@"), " ")
			tags = append(tags, name)
		}
	}

	return tags
}

func otherwise(branch engine.Match) []engine.Match {
	owner := branch
	if branch.Kind() == "Stmt_ElseIf" {
		owner = branch.Parent()
	}
	if owner.Kind() != "Stmt_If" {
		return nil
	}
	rungs := owner.ChildrenIn("elseifs")
	next, found := 0, branch.Kind() != "Stmt_ElseIf"
	for at, rung := range rungs {
		if rung.Node() == branch.Node() {
			next, found = at+1, true
		}
	}
	if !found {
		return nil
	}
	if next < len(rungs) {
		return []engine.Match{rungs[next]}
	}

	return owner.Child("else").Body()
}

func condition(held engine.Match) engine.Match {
	if !slices.Contains([]string{"Stmt_If", "Stmt_ElseIf", "Stmt_While", "Stmt_Do", "Expr_Ternary"}, held.Kind()) {
		return engine.Match{}
	}

	return held.Child("cond")
}

func expectsTestFailure(caught engine.Match) bool {
	if caught.Kind() != "Stmt_Catch" || len(caught.Body()) != 0 {
		return false
	}
	program := ProgramOf(caught.Codebase())
	if !program.IsA(EnclosingClassName(caught), `PHPUnit\Framework\TestCase`) {
		return false
	}
	if slices.ContainsFunc(caught.ChildrenIn("types"), func(held engine.Match) bool {
		class := strings.TrimLeft(held.Name(), `\`)
		return slices.Contains([]string{"Throwable", "Exception", "RuntimeException", `PHPUnit\Exception`}, class) ||
			program.IsA(`PHPUnit\Framework\AssertionFailedError`, class)
	}) {
		return false
	}
	statements := caught.Parent().ChildrenIn("stmts")
	if len(statements) == 0 {
		return false
	}
	call := statements[len(statements)-1].Child("expr")
	return call.Kind() == "Expr_MethodCall" && variableName(call.Child("var")) == "this" && CallName(call) == "fail"
}

func messageParts(message engine.Match) []engine.Match {
	if message.Kind() == "Expr_BinaryOp_Concat" {
		return []engine.Match{message.Child("left"), message.Child("right")}
	}
	call := Node{Match: message}
	if call.CallsFunction("sprintf") {
		return []engine.Match{call.Argument(0).Match}
	}

	return nil
}

var constantSides = []string{"Scalar_String", "Scalar_Int", "Scalar_Float", "Expr_ClassConstFetch", "Expr_ConstFetch"}

func comparisonSubject(held engine.Match) engine.Match {
	if !slices.Contains([]string{"===", "==", "!==", "!="}, held.Operator()) {
		return engine.Match{}
	}

	return engine.VariedSide(held.Child("left"), held.Child("right"), isConstantSide)
}

func isConstantSide(side engine.Match) bool {
	return slices.Contains(constantSides, side.Kind())
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "Stmt_Foreach" {
		return ""
	}
	element := loop.Child("valueVar")
	if element.Kind() != "Expr_Variable" {
		return ""
	}

	return element.Name()
}

func computes(held engine.Match) bool {
	kind := held.Kind()
	for _, prefix := range []string{"Expr_BinaryOp_", "Expr_AssignOp_"} {
		if strings.HasPrefix(kind, prefix) {
			return true
		}
	}

	return slices.Contains([]string{"Expr_UnaryMinus", "Expr_UnaryPlus", "Expr_BooleanNot", "Expr_BitwiseNot", "Expr_PreInc", "Expr_PreDec", "Expr_PostInc", "Expr_PostDec"}, kind)
}
