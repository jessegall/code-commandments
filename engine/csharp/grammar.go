package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/efcore"
)

func init() {
	engine.ReadAs(contract.CSharp, engine.Grammar{
		ExecutesQuery: efcore.ExecutesQuery,
		NullDefault:   nullSwitchDefault,
		ListedClasses: listedClasses,
		Types: func(codebase *engine.Codebase) engine.TypeSystem {
			return engine.TypesFromDeclarations(codebase.Of(contract.CSharp))
		},
		SpelledFolder: spelledFolder,
		Arguments:     arguments,
		Receiver:      receiver,
		Declares:      engine.KindIn("SingleVariableDesignation", "DeclarationPattern"),
		Binds:         engine.KindIn("LocalDeclarationStatement"),
		Members:       engine.InFields("Members"),
		DeclaredName: func(declaration engine.Match) string {
			return Node{Match: declaration}.DeclaredName()
		},
		Parameters:  parameters,
		Extends:     func(declaration engine.Match) []engine.Match { return basesWhere(declaration, false) },
		Implements:  func(declaration engine.Match) []engine.Match { return basesWhere(declaration, true) },
		Annotations: attributes,
		TypeOf:      func(node engine.Match) string { return Node{Match: node}.Type().Name() },
		TypeKind: engine.Kinds(map[string]string{"ClassDeclaration": "class", "InterfaceDeclaration": "interface",
			"EnumDeclaration": "enum", "RecordDeclaration": "record", "RecordStructDeclaration": "record", "StructDeclaration": "struct"}),
		DeclaresMethods: func(declared engine.Match) bool {
			return slices.ContainsFunc(declared.Members(), engine.KindIn("MethodDeclaration"))
		},
		ReturnType:    engine.InField("ReturnType"),
		ParameterType: engine.InField("Type"),
		Constructs:    engine.OfKind("ObjectCreationExpression", "Type"),
		DocTags:       xmlTags,
		Implicit: engine.KindIn("ConstructorDeclaration", "DestructorDeclaration", "OperatorDeclaration",
			"ConversionOperatorDeclaration"),
		Inherited:          func(member engine.Match) bool { return Node{Match: member}.IsInherited() },
		NamespaceSeparator: ".",
		NamespaceOf: func(symbol string) string {
			member, _, _ := strings.Cut(strings.TrimPrefix(symbol, "global::"), "(")

			return engine.Before(member, ".")
		},
		AnnotationNames: func(name string) []string { return []string{name, name + "Attribute"} },
		Labels: func(name engine.Match) bool {
			return name.Parent().Kind() == "NameColon" || name.Parent().Kind() == "NameEquals"
		},
		Continues: func(branch engine.Match) bool { return Node{Match: branch}.IsElseIf() },
		Steps:     steps,
		Fallback:  func(held engine.Match) engine.Match { return Node{Match: held}.Fallback().Match },
		StableFallback: func(held engine.Match) bool {
			return held.Is(engine.Literal) || held.IsConstant() || (Node{Match: held}).IsEmptyScalar()
		},
		Grouping:            engine.KindIn("ParenthesizedExpression"),
		Thrown:              thrown,
		EmptyCollection:     func(held engine.Match) bool { return Node{Match: held}.IsEmptyCollection() },
		ConstructedType:     func(held engine.Match) string { return Node{Match: held}.Target().Type() },
		GenericFailures:     genericExceptions,
		GenericMessage:      true,
		TakesFailureMessage: takesFailureMessage,
		BroadCatch:          engine.As(Node.IsBroadCatch),
		Hashing:             hashing,
		CallConversion:      callConversion,
		BooleanTypes:        []string{"bool", "System.Boolean", "global::System.Boolean"},
		ArgumentLookup:      argumentLookup,
		ReturnedValue:       engine.InField("Expression"),
		InitializedValue:    initializedValue,
		AssignmentSides:     engine.ReadPair("Left", "Right"),
		Getter:              getterWithoutCapturedParameters,
		TypeDispatch:        typeDispatch,
		SideTables:          sideTables,
		ConstClass: func(m engine.Match) bool {
			n := Node{Match: m}
			return n.IsConstClassEnum() && Of(m.Codebase()).ComparesAsACase(n.Symbol())
		},
		EnumValues: func(match engine.Match) []string {
			if match.TypeKind() != "enum" {
				return nil
			}
			return lowerWords(Of(match.Codebase()).EnumMembers(match.Identity()))
		},
		EnumCatalog: enumCatalog,
		EnumMembership: func(match engine.Match) []string {
			n := Node{Match: match}
			if !n.Is("InvocationExpression", "IsPatternExpression") {
				return nil
			}
			return lowerWords(n.MembershipLiterals())
		},
		EnumChainOwner: func(match engine.Match) string {
			n := Node{Match: match}
			if !n.Is("LogicalOrExpression", "OrPattern") || !n.IsGroupTestRoot() || n.SitsInAnExpressionTree() {
				return ""
			}
			program := Of(match.Codebase())
			for _, group := range n.EnumsTestedAsAGroup() {
				if program.DeclaresEnum(group) {
					return group
				}
			}
			return ""
		},
		PlaceholderData: func(match engine.Match) bool {
			n := Node{Match: match}
			return n.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") && !n.IsNullObjectOfItsType() && !n.IsInTest() && Of(match.Codebase()).FillsRecordWithBlank(n)
		},
		BlankDefault:     blankDefault,
		DeNulledFinder:   deNulledFinder,
		ManufacturesFill: manufacturesFill,
		CoalescedLoop:    coalescedLoop,
		NullForgiving:    engine.KindIn("SuppressNullableWarningExpression"),
		CapabilityTest:   capabilityTest,
		NullableFields:   nullableFields,
		KeyedStore:       keyedStore,
		DefaultValue:     defaultValue,
		CallbackTarget:   callbackTarget,
		CallableTypes:    []string{"global::System.Action", "global::System.Func"},
		Decode:           decodesJSON,
		Encode:           encodesJSON,
		UntypedReturn:    returnsUntypedData,
		CeremonyDoc:      ceremonyDoc,
		DocParagraphs:    func(comment engine.Commented) int { return (Comment{Commented: comment}).Paragraphs() },
		CopyBody: func(match engine.Match) []engine.Match {
			function := Node{Match: match}
			if function.RunsABody() && function.FunctionBody().Exists() {
				return []engine.Match{function.FunctionBody().Match}
			}
			return nil
		},
		UnnamedVocabulary: unnamedVocabulary,
		Guard:             guard,
		IsConstructor:     func(held engine.Match) bool { return held.Kind() == "ConstructorDeclaration" },
		Body:              body,
		JumpTarget:        engine.KindIn("LabeledStatement"),
		Otherwise:         otherwise,
		Condition:         condition,
		ComparisonSubject: func(held engine.Match) engine.Match { return Node{Match: held}.ComparisonSubject().Match },
		ElementName:       elementName,
		Computes:          computes,
		Documentable:      documentable,
		CodeWords:         func(node engine.Match) []string { return Node{Match: node}.CodeWords() },
	})

}

var sentTo = []string{"SimpleMemberAccessExpression", "PointerMemberAccessExpression", "ElementAccessExpression", "ConditionalAccessExpression"}

func argumentLookup(node engine.Match) (engine.Match, engine.Match) {
	if node.Kind() != "ElementAccessExpression" {
		return engine.Match{}, engine.Match{}
	}
	return node.Receiver(), node.Child("ArgumentList").Child("Arguments").Child("Expression")
}

func initializedValue(node engine.Match) engine.Match {
	if node.Kind() != "VariableDeclarator" {
		return engine.Match{}
	}
	return node.Child("Initializer").Child("Value")
}

func receiver(node engine.Match) engine.Match {
	if slices.Contains(sentTo, node.Kind()) {
		return node.Child("Expression")
	}

	if node.Kind() == "MemberBindingExpression" {
		return conditioned(node)
	}

	if node.Kind() != "InvocationExpression" && node.Kind() != "ObjectCreationExpression" {
		return engine.Match{}
	}

	return receiver(node.Child("Expression"))
}

func conditioned(binding engine.Match) engine.Match {
	for above := binding.Parent(); above.Exists(); above = above.Parent() {
		if above.Kind() == "ConditionalAccessExpression" {
			return above.Child("Expression")
		}
	}

	return engine.Match{}
}

func documentable(node engine.Match) bool {
	held := Node{Match: node}

	return !held.IsExpression() && held.hierarchyRole() != "other"
}

func xmlTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		for _, line := range strings.Split(comment, "\n") {
			line = strings.TrimSpace(strings.TrimLeft(strings.TrimSpace(line), "/"))
			if !strings.HasPrefix(line, "<") || strings.HasPrefix(line, "</") {
				continue
			}

			name := strings.FieldsFunc(line[1:], func(r rune) bool { return r == ' ' || r == '>' || r == '/' })
			if len(name) > 0 {
				tags = append(tags, name[0])
			}
		}
	}

	return tags
}

func arguments(match engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range (Node{Match: match}).Arguments() {
		handed = append(handed, argument.Match)
	}

	return handed
}

func basesWhere(declaration engine.Match, interfaces bool) []engine.Match {
	extendsAll := declaration.TypeKind() == "interface"
	if extendsAll && interfaces {
		return nil
	}

	var named []engine.Match
	for _, base := range declaration.Child("BaseList").ChildrenIn("Types") {
		if written := base.Child("Type"); extendsAll || namesInterface(written) == interfaces {
			named = append(named, written)
		}
	}

	return named
}

func namesInterface(written engine.Match) bool {
	symbol := written.Refers()
	if declarations := written.Codebase().Declarations(symbol); len(declarations) > 0 {
		return declarations[0].TypeKind() == "interface"
	}

	return written.OutsideKind(symbol) == "interface"
}

func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, list := range declaration.ChildrenIn("AttributeLists") {
		for _, attribute := range list.ChildrenIn("Attributes") {
			names = append(names, attribute.Child("Name"))
		}
	}

	return names
}

func parameters(function engine.Match) []engine.Match {
	declared := function.ChildrenIn("Parameter")
	for _, parameter := range (Node{Match: function}).Parameters() {
		declared = append(declared, parameter.Match)
	}

	return declared
}

func body(held engine.Match) []engine.Match {
	for _, child := range (Node{Match: held}).Children() {
		if !child.IsStatement() {
			continue
		}
		if child.Is("Block") {
			return child.Match.Children()
		}

		return []engine.Match{child.Match}
	}

	return nil
}

func otherwise(branch engine.Match) []engine.Match {
	for _, child := range (Node{Match: branch}).Children() {
		if child.Is("ElseClause") {
			return body(child.Match)
		}
	}

	return nil
}

func condition(held engine.Match) engine.Match {
	node := Node{Match: held}
	if !node.Is("IfStatement", "WhileStatement", "DoStatement", "ConditionalExpression") {
		return engine.Match{}
	}
	for _, child := range node.All() {
		if child.IsExpression() {
			return child.Match
		}
	}

	return engine.Match{}
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "ForEachStatement" {
		return ""
	}

	return loop.Name()
}

func computes(held engine.Match) bool {
	return Node{Match: held}.Is(
		"AddExpression", "SubtractExpression", "MultiplyExpression", "DivideExpression", "ModuloExpression",
		"LeftShiftExpression", "RightShiftExpression", "UnsignedRightShiftExpression",
		"BitwiseAndExpression", "BitwiseOrExpression", "ExclusiveOrExpression",
		"UnaryMinusExpression", "UnaryPlusExpression", "BitwiseNotExpression", "LogicalNotExpression",
		"PreIncrementExpression", "PreDecrementExpression", "PostIncrementExpression", "PostDecrementExpression",
		"SimpleAssignmentExpression", "AddAssignmentExpression", "SubtractAssignmentExpression",
		"MultiplyAssignmentExpression", "DivideAssignmentExpression", "ModuloAssignmentExpression",
		"AndAssignmentExpression", "OrAssignmentExpression", "ExclusiveOrAssignmentExpression",
		"LeftShiftAssignmentExpression", "RightShiftAssignmentExpression", "CoalesceAssignmentExpression",
	)
}

func steps(loop engine.Match) []engine.Match {
	var found []engine.Match
	for _, child := range loop.Children() {
		if (Node{Match: child}).IsStep() {
			found = append(found, child)
		}
	}

	return found
}

func takesFailureMessage(construction engine.Match) bool {
	for _, declaration := range construction.Codebase().Declarations(construction.TargetSymbol()) {
		if parameters := declaration.Parameters(); len(parameters) > 0 {
			return parameters[0].Name() == "message"
		}
	}
	for _, declaration := range construction.Codebase().Declarations(construction.ConstructedType()) {
		if parameters := declaration.Child("ParameterList").ChildrenIn("Parameters"); len(parameters) > 0 {
			return parameters[0].Name() == "message"
		}
	}
	return true
}

func thrown(throw engine.Match) engine.Match {
	expressions := Node{Match: throw}.Expressions()
	if !throw.Is(engine.Throw) || len(expressions) == 0 {
		return engine.Match{}
	}

	return expressions[0].Match
}

func lowerWords(words []string) []string {
	var lower []string
	for _, word := range words {
		lower = append(lower, strings.ToLower(word))
	}
	return lower
}
