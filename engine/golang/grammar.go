package golang

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.Go, engine.Grammar{
		SpelledFolder:      spelledFolder,
		Receiver:           receiver,
		ErrorHandler:       checkedError,
		Thrown:             returnedFailure,
		Cause:              wrappedFailure,
		EmptyCollection:    emptyCollection,
		TypeKind:           typeKind,
		Types:              typeSystem,
		MethodOwner:        func(method engine.Match) string { return (Node{Match: method}).OwnerSymbol() },
		Declares:           shortDeclaration,
		Binds:              shortDeclaration,
		Parameters:         parameters,
		Members:            members,
		DeclaresMethods:    declaresMethods,
		IsConstructor:      isConstructor,
		PrivateMember:      privateMember,
		Collaborator:       collaborator,
		ParameterType:      engine.InField("type"),
		ReturnType:         engine.InField("results"),
		Continues:          continues,
		Steps:              engine.InFields("post"),
		TestFile:           testFile,
		TestAssertion:      testAssertion,
		HoldsPublicState:   passesPublicStruct,
		Body:               body,
		Otherwise:          otherwise,
		Condition:          condition,
		BindsItsOwnTest:    bindsItsOwnTest,
		ElementName:        elementName,
		ComparisonSubject:  comparisonSubject,
		JumpTarget:         engine.KindIn("LabeledStmt"),
		NamespaceOf:        namespaceOf,
		ImportModule:       importedModule,
		ModuleKey:          packagePath,
		ModuleLayer:        packagePath,
		Hashing:            hashing,
		ScalarTypes:        []string{"string", "bool", "int", "int8", "int16", "int32", "int64", "uint", "uint8", "uint16", "uint32", "uint64", "float32", "float64", "byte", "rune"},
		BooleanTypes:       []string{"bool"},
		ArgumentLookup:     engine.IndexedArgument("IndexExpr", "x", "index"),
		InitializedValue:   engine.OfKind("AssignStmt", "rhs"),
		AssignmentSides:    engine.ReadPair("lhs", "rhs"),
		RecordKeys:         recordKeys,
		JoinedLines:        joinedLines,
		NullDefault:        nullSwitchDefault,
		StaticStorage:      staticStorage,
		IsValueClass:       engine.Match.DeclaresValueEquality,
		CapabilityTest:     capabilityTest,
		SelfReceiver:       selfReceiver,
		NullableFields:     nullableFields,
		Encode:             func(call engine.Match) bool { return call.TargetSymbol() == "encoding/json.Marshal" },
		DecodedDestination: decodedDestination,
		UntypedReturn:      returnsUntypedData,
		CommentReferences:  commentReferences,
		Keywords:           keywords,
		DocumentedSubject:  documentedSubject,
		CommentProse:       commentProse,
		VocabularyWords:    vocabularyWords,
		Arguments:          engine.InFields("args"),
	})
}

func receiver(match engine.Match) engine.Match {
	if match.Kind() == "SelectorExpr" {
		return match.Child("x")
	}
	if match.Kind() == "CallExpr" {
		return match.Child("fun").Child("x")
	}

	return engine.Match{}
}

func parameters(function engine.Match) []engine.Match {
	return function.Child("type").Child("params").Children()
}

func continues(branch engine.Match) bool {
	return branch.Kind() == "IfStmt" && branch.Field() == "else"
}

func testFile(judged, path string) bool {
	if strings.HasSuffix(path, "_test.go") {
		return true
	}

	return strings.Contains(strings.ReplaceAll(path, "\\", "/"), "/testdata/")
}

func namespaceOf(symbol string) string {
	at := strings.LastIndex(symbol, ".")
	if at < 0 {
		return ""
	}

	return symbol[:at]
}

func importedModule(node engine.Match) string {
	name, _ := node.Child("path").Text()
	return name
}

func packagePath(root engine.Match) string {
	if file := root.Source(); file != nil {
		return file.Module
	}
	return ""
}

func body(held engine.Match) []engine.Match {
	return held.Child("body").Children()
}

func otherwise(branch engine.Match) []engine.Match {
	held := branch.Child("else")
	if held.Kind() == "BlockStmt" {
		return held.Children()
	}
	if !held.Exists() {
		return nil
	}

	return []engine.Match{held}
}

func condition(held engine.Match) engine.Match {
	if held.Kind() != "IfStmt" && held.Kind() != "ForStmt" {
		return engine.Match{}
	}

	return held.Child("cond")
}

func comparisonSubject(held engine.Match) engine.Match {
	if held.Kind() != "BinaryExpr" || held.Operator() != "==" {
		return engine.Match{}
	}
	return engine.VariedSide(held.Child("x"), held.Child("y"), engine.Match.IsConstant)
}

func bindsItsOwnTest(branch engine.Match) bool {
	return branch.Kind() == "IfStmt" && branch.Child("init").Exists()
}

func shortDeclaration(statement engine.Match) bool {
	return statement.Kind() == "AssignStmt" && statement.Operator() == ":="
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "RangeStmt" {
		return ""
	}
	value := loop.Child("value")
	if value.Kind() != "Ident" || value.Name() == "_" {
		return ""
	}

	return value.Name()
}
