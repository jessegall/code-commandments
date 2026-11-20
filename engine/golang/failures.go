package golang

import (
	"regexp"

	"github.com/jessegall/code-commandments/engine"
)

func checkedError(branch engine.Match) engine.Match {
	if branch.Kind() != "IfStmt" {
		return engine.Match{}
	}
	test := branch.Child("cond")
	if test.Operator() != "!=" || !(Node{Match: test}).IsErrorCheck() {
		return engine.Match{}
	}
	if checked := test.Child("x"); isError(checked) {
		return checked
	}

	return test.Child("y")
}

func returnedFailure(statement engine.Match) engine.Match {
	if !statement.Is(engine.Return) {
		return engine.Match{}
	}
	for _, result := range statement.ChildrenIn("results") {
		switch result.TargetSymbol() {
		case "errors.New", "fmt.Errorf":
			return result
		}
	}

	return engine.Match{}
}

var wrapVerb = regexp.MustCompile(`(?:^|[^%])(?:%%)*%(?:\[[0-9]+\])?w`)

func wrappedFailure(statement engine.Match) engine.Match {
	call := returnedFailure(statement)
	if call.TargetSymbol() != "fmt.Errorf" {
		return engine.Match{}
	}
	arguments := call.Arguments()
	if len(arguments) < 2 {
		return engine.Match{}
	}
	format, literal := arguments[0].Text()
	if !literal || !wrapVerb.MatchString(format) {
		return engine.Match{}
	}
	for _, argument := range arguments[1:] {
		if isError(argument) {
			return argument
		}
	}

	return engine.Match{}
}

func emptyCollection(held engine.Match) bool {
	if held.Kind() != "CompositeLit" || len(held.ChildrenIn("elts")) > 0 {
		return false
	}
	switch kind := held.Child("type"); kind.Kind() {
	case "MapType":
		return true
	case "ArrayType":
		return !kind.Child("len").Exists()
	}

	return false
}
