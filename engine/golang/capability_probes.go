package golang

import "github.com/jessegall/code-commandments/engine"

func capabilityTest(branch, check engine.Match) engine.CapabilityTest {
	bind := branch.Child("init")
	left, right := bind.ChildrenIn("lhs"), bind.ChildrenIn("rhs")
	if bind.Operator() != ":=" || len(left) != 2 || len(right) != 1 || right[0].Kind() != "TypeAssertExpr" {
		return engine.CapabilityTest{}
	}
	if !check.Is(engine.Identifier) || check.Name() != left[1].Name() {
		return engine.CapabilityTest{}
	}
	return engine.CapabilityTest{Subject: right[0].Child("x"), Alias: left[0].Name()}
}

func selfReceiver(reference engine.Match) bool {
	if !reference.Is(engine.Identifier) {
		return false
	}
	receiver := reference.EnclosingFunction().Child("recv").Child("list").Child("names")
	return receiver.Exists() && receiver.Name() == reference.Name()
}
