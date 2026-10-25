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
