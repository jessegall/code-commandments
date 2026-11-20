package frontend

import (
	"path"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
)

func init() {
	for _, language := range []contract.Language{contract.Vue, contract.TypeScript} {
		engine.ReadAs(language, engine.Grammar{
			Arguments:          engine.InFields("arguments"),
			Members:            typescript.MembersWithParameterFields,
			Parameters:         engine.InFields("parameters"),
			OwnsRecordBehavior: engine.KindIn("TypeAliasDeclaration"),
			ReactiveMember:     typescript.ReactiveMember,
			Extends:            heritage("extends"),
			Implements:         heritage("implements"),
			Annotations:        decorators,
			TypeKind:           engine.Kinds(map[string]string{"ClassDeclaration": "class", "ClassExpression": "class", "InterfaceDeclaration": "interface", "EnumDeclaration": "enum"}),
			DeclaresMethods: func(declared engine.Match) bool {
				return slices.ContainsFunc(declared.Members(), engine.KindIn("MethodSignature"))
			},
			ReturnType:      engine.InField("type"),
			ParameterType:   engine.InField("type"),
			Constructs:      engine.OfKind("NewExpression", "expression"),
			TypeOf:          func(node engine.Match) string { return typescript.Of(node).TypeName() },
			Receiver:        receiver,
			Condition:       condition,
			RenderCondition: renderCondition,
			TypeTest:        typeTest,
			Declares:        engine.KindIn("VariableDeclaration", "Parameter", "BindingElement"),
			Binds:           engine.KindIn("VariableStatement"),
			DocTags:         jsDocTags,
			WritesAttribute: func(element engine.Match, name string) bool {
				return vue.Of(element).Writes(name)
			},
			TestFile:      testFile,
			Implicit:      engine.KindIn("Constructor"),
			IsConstructor: engine.KindIn("Constructor"),
			NamespaceOf:   func(symbol string) string { return engine.Before(symbol, "#") },
			ImportModule:  engine.Match.Resolves,
			ModuleKey:     engine.Match.File,
			ModuleLayer:   func(root engine.Match) string { return path.Dir(root.FromProject()) },
			Labels: func(name engine.Match) bool {
				return name.Parent().Kind() == "PropertyAssignment" && name.Node().Field == "name"
			},
			Continues: func(branch engine.Match) bool {
				return branch.Kind() == "IfStatement" && branch.Node().Field == "elseStatement"
			},
			Steps:    engine.InFields("incrementor"),
			Grouping: engine.KindIn("ParenthesizedExpression"),
			Thrown:   engine.OfKind("ThrowStatement", "expression"),
			DeclaredName: func(declaration engine.Match) string {
				if declaration.Kind() != "CatchClause" {
					return ""
				}

				return declaration.Child("variableDeclaration").Name()
			},
			EmptyCollection: func(held engine.Match) bool {
				return held.Kind() == "ArrayLiteralExpression" && len(held.Children()) == 0
			},
			ConstructedType:   func(construction engine.Match) string { return construction.Constructed().Name() },
			GenericFailures:   []string{"Error"},
			Hashing:           typescript.Hashing,
			ScalarTypes:       []string{"string", "number", "boolean", "bigint"},
			BooleanTypes:      []string{"boolean"},
			ArgumentLookup:    engine.IndexedArgument("ElementAccessExpression", "expression", "argumentExpression"),
			InitializedValue:  engine.OfKind("VariableDeclaration", "initializer"),
			AssignmentSides:   engine.ReadPair("left", "right"),
			RecordKeys:        typescript.RecordKeys,
			ReactiveRecord:    typescript.ReturnsVueState,
			MemberOrder:       typescript.MemberOrder,
			JoinedLines:       typescript.JoinedLines,
			TupleSlots:        typescript.TupleSlots,
			StaticStorage:     typescript.StaticStorage,
			IsValueClass:      engine.Match.DeclaresValueEquality,
			PrivateMember:     typescript.PrivateMember,
			Getter:            engine.KindIn("GetAccessor"),
			Collaborator:      typescript.ConstructorCollaborator,
			ArrowReturn:       typescript.RedundantArrowReturn,
			NullDefault:       typescript.NullSwitchDefault,
			DispatchTest:      typescript.DispatchTest,
			ConstClass:        typescript.IsConstClassEnum,
			EnumTable:         typescript.KeepsEnumSideTable,
			SideTables:        typescript.SideTables,
			EnumValues:        typescript.EnumValues,
			EnumCase:          typescript.EnumCase,
			EnumMembership:    typescript.EnumMembership,
			EnumArms:          typescript.EnumArms,
			PlaceholderData:   typescript.FillsDataWithPlaceholder,
			Fallback:          fallback,
			ConditionalArms:   conditionalArms,
			Spreads:           engine.KindIn("SpreadElement", "SpreadAssignment"),
			LiteralCollection: engine.KindIn("ArrayLiteralExpression", "ObjectLiteralExpression"),
			Iterated:          iterated,
			NullForgiving:     engine.KindIn("NonNullExpression"),
			CapabilityTest: func(_, test engine.Match) engine.CapabilityTest {
				if test.Operator() == "instanceof" {
					return engine.CapabilityTest{Subject: test.Child("left")}
				}
				return engine.CapabilityTest{}
			},
			KeyedStore:        typescript.KeyedStore,
			DefaultValue:      engine.InField("initializer"),
			Decode:            typescript.DecodesJSON,
			Encode:            typescript.EncodesJSON,
			UntypedReturn:     typescript.ReturnsUntypedData,
			CommentReferences: commentReferences,
			Keywords:          keywords,
			CeremonyDoc:       ceremonyDoc,
			VocabularyWords:   typescript.VocabularyWords,
			CopyBody:          typescript.CopyBody,
			CopyExclusions:    typescript.CopyExclusions,
			TwinPairs: func(codebase *engine.Codebase) engine.TwinPairs {
				return engine.ResolvedTwinPairs(typescript.In(codebase).Codebase)
			},
			SpelledFolder: typescript.SpelledFolder,
			ListedClasses: typescript.ListedClasses,
			Types: func(codebase *engine.Codebase) engine.TypeSystem {
				return engine.TypesFromDeclarations(typescript.In(codebase).Codebase)
			},
			Body:              body,
			Otherwise:         otherwise,
			ComparisonSubject: comparisonSubject,
			JumpTarget:        engine.KindIn("LabeledStatement"),
		})

	}
}

func testFile(file, _ string) bool {
	name := path.Base(strings.ReplaceAll(file, `\`, "/"))

	return strings.Contains(name, ".test.") || strings.Contains(name, ".spec.") ||
		engine.InFolderNamed(file, "__tests__", "tests", "test")
}

func heritage(keyword string) func(engine.Match) []engine.Match {
	return func(declaration engine.Match) []engine.Match {
		var named []engine.Match
		for _, clause := range declaration.ChildrenIn("heritageClauses") {
			if clause.Node().Operator != keyword {
				continue
			}

			for _, written := range clause.ChildrenIn("types") {
				named = append(named, written.Child("expression"))
			}
		}

		return named
	}
}

func decorators(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, modifier := range declaration.ChildrenIn("modifiers") {
		if modifier.Kind() != "Decorator" {
			continue
		}

		decorator := modifier.Child("expression")
		if decorator.Is(engine.Call) {
			decorator = decorator.Child("expression")
		}

		names = append(names, decorator)
	}

	return names
}

func atTags(comment string) []string {
	var tags []string
	for _, line := range strings.Split(comment, "\n") {
		line = strings.TrimLeft(strings.TrimSpace(line), "/*")
		word, _, _ := strings.Cut(strings.TrimSpace(line), " ")
		if name, tagged := strings.CutPrefix(word, "@"); tagged && name != "" {
			tags = append(tags, strings.TrimRight(name, "{}"))
		}
	}

	return tags
}

func jsDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		tags = append(tags, atTags(comment)...)
	}

	return tags
}

var sentTo = []string{"PropertyAccessExpression", "ElementAccessExpression"}

func receiver(node engine.Match) engine.Match {
	if slices.Contains(sentTo, node.Kind()) {
		return node.Child("expression")
	}

	if node.Kind() != "CallExpression" && node.Kind() != "NewExpression" {
		return engine.Match{}
	}

	return receiver(node.Child("expression"))
}

var asked = map[string]string{
	"IfStatement":           "expression",
	"WhileStatement":        "expression",
	"DoStatement":           "expression",
	"SwitchStatement":       "expression",
	"ConditionalExpression": "condition",
	"ForStatement":          "condition",
}

func condition(held engine.Match) engine.Match {
	field, decides := asked[held.Kind()]
	if !decides {
		return engine.Match{}
	}

	return held.Child(field)
}

func renderCondition(node engine.Match) engine.Match {
	if node.Kind() != "Directive" {
		return engine.Match{}
	}
	directive := vue.Directive{Match: node}
	if !directive.Named(vue.If) && !directive.Named(vue.ElseIf) {
		return engine.Match{}
	}

	return directive.Value()
}

func typeTest(node engine.Match) bool {
	if node.Kind() != "BinaryExpression" {
		return false
	}
	switch node.Operator() {
	case "instanceof":
		return true
	case "===", "!==", "==", "!=":
		left, right := node.Child("left"), node.Child("right")

		return left.Kind() == "TypeOfExpression" && right.Kind() == "StringLiteral" || right.Kind() == "TypeOfExpression" && left.Kind() == "StringLiteral"
	}

	return false
}

var bodies = map[string]string{
	"IfStatement":      "thenStatement",
	"ForStatement":     "statement",
	"ForInStatement":   "statement",
	"ForOfStatement":   "statement",
	"WhileStatement":   "statement",
	"DoStatement":      "statement",
	"CatchClause":      "block",
	"TryStatement":     "tryBlock",
	"LabeledStatement": "statement",
}

func body(held engine.Match) []engine.Match {
	if held.Kind() == "VariableDeclaration" && held.Child("initializer").Kind() == "ArrowFunction" {
		return body(held.Child("initializer"))
	}
	if field, holds := bodies[held.Kind()]; holds {
		return statementsOf(held.Child(field))
	}

	return statementsOf(held.Child("body"))
}

func otherwise(branch engine.Match) []engine.Match {
	if branch.Kind() != "IfStatement" {
		return nil
	}

	return statementsOf(branch.Child("elseStatement"))
}

func statementsOf(held engine.Match) []engine.Match {
	if held.Kind() == "Block" {
		return held.ChildrenIn("statements")
	}
	if !held.Exists() {
		return nil
	}

	return []engine.Match{held}
}

var equalities = []string{"===", "==", "!==", "!="}

func comparisonSubject(held engine.Match) engine.Match {
	if held.Kind() != "BinaryExpression" || !slices.Contains(equalities, held.Operator()) {
		return engine.Match{}
	}

	return engine.VariedSide(held.Child("left"), held.Child("right"), isConstantSide)
}

func isConstantSide(side engine.Match) bool {
	return side.Is(engine.Literal) || side.IsConstant()
}
