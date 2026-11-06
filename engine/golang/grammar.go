package golang

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.Go, engine.Grammar{
		Receiver:          receiver,
		Parameters:        parameters,
		ReturnType:        engine.InField("results"),
		Continues:         continues,
		TestFile:          testFile,
		Body:              body,
		Otherwise:         otherwise,
		Condition:         condition,
		BindsItsOwnTest:   bindsItsOwnTest,
		ElementName:       elementName,
		ComparisonSubject: comparisonSubject,
		NamespaceOf:       namespaceOf,
		BodyHash:          BodyHash,
		NodeHash:          NodeHash,
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
	left, right := held.Child("x"), held.Child("y")
	switch {
	case !left.IsConstant() && right.IsConstant():
		return left
	case left.IsConstant() && !right.IsConstant():
		return right
	}

	return engine.Match{}
}

func bindsItsOwnTest(branch engine.Match) bool {
	return branch.Kind() == "IfStmt" && branch.Child("init").Exists()
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
