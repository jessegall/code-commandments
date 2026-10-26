package golang

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.Go, engine.Grammar{
		Receiver:   receiver,
		Parameters: parameters,
		ReturnType: engine.InField("results"),
		Continues:  continues,
		TestFile:   testFile,
		// Go always braces a body, so its statements are the block's; an `else` holds either that block or the
		// `if` of an `else if`.
		Body:              body,
		Otherwise:         otherwise,
		Condition:         condition,
		BindsItsOwnTest:   bindsItsOwnTest,
		ComparisonSubject: comparisonSubject,
		// A Go symbol is a package path and the names under it, so the namespace is everything before the last
		// name: example.com/shop/cart.Cart.Total belongs to example.com/shop/cart.
		NamespaceOf: namespaceOf,
	})
}

// receiver is the value a member access or a call is sent to: the `order` of `order.Ship()`. Go writes both as a
// selector, whose own value is the field x.
func receiver(match engine.Match) engine.Match {
	if match.Kind() == "SelectorExpr" {
		return match.Child("x")
	}
	if match.Kind() == "CallExpr" {
		return match.Child("fun").Child("x")
	}

	return engine.Match{}
}

// parameters are a function's own parameters: the fields of its type's parameter list. A method's receiver is a
// field list of its own and no parameter of the call, so it is not among them.
func parameters(function engine.Match) []engine.Match {
	return function.Child("type").Child("params").Children()
}

// continues says whether a branch continues the one it sits in rather than nesting inside it: Go writes
// `else if` as an `if` filling the `else` of the one before it, so the two are one choice to a reader.
func continues(branch engine.Match) bool {
	return branch.Kind() == "IfStmt" && branch.Field() == "else"
}

// testFile says whether a Go file is a test's, as the toolchain decides it: a name ending in _test.go, which is
// the only thing that makes a file a test in Go, and the testdata folder, which the toolchain never compiles.
func testFile(judged, path string) bool {
	if strings.HasSuffix(path, "_test.go") {
		return true
	}

	return strings.Contains(strings.ReplaceAll(path, "\\", "/"), "/testdata/")
}

// namespaceOf is the package a symbol belongs to: everything before the last name in its id.
func namespaceOf(symbol string) string {
	at := strings.LastIndex(symbol, ".")
	if at < 0 {
		return ""
	}

	return symbol[:at]
}

// body are the statements a construct runs as its own: the statements of the block it holds. Go braces every
// body, so there is always a block to read through, and a construct holding none answers with nothing.
func body(held engine.Match) []engine.Match {
	return held.Child("body").Children()
}

// otherwise are the statements a branch runs when its condition does not hold. Go writes the else as either a
// block of statements or the `if` of an `else if`, which stands as the one statement.
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

// condition is what a branch or a loop decides by. A `switch` holds a tag its cases are matched against and a
// `select` holds nothing at all, neither of which is a condition with an inverse; a `range` decides by nothing,
// and a `for` by the cond it carries, which a bare `for {}` does not.
func condition(held engine.Match) engine.Match {
	if held.Kind() != "IfStmt" && held.Kind() != "ForStmt" {
		return engine.Match{}
	}

	return held.Child("cond")
}

// comparisonSubject is what an `==` tests against a constant: the side that is no constant, when the other one
// is. Go's constants are its literals and the names a const declaration binds, both of which the type checker
// calls constant, so a comparison against a named const of a defined type reads as one too.
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

// bindsItsOwnTest says whether the branch binds a name in its header for its own body: Go writes that as the
// `init` statement of the `if`, before the semicolon, and scopes what it binds to the branch alone.
func bindsItsOwnTest(branch engine.Match) bool {
	return branch.Kind() == "IfStmt" && branch.Child("init").Exists()
}
