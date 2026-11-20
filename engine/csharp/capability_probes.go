package csharp

import "github.com/jessegall/code-commandments/engine"

func capabilityTest(branch engine.Match) engine.CapabilityTest {
	test := Node{Match: branch.Condition()}
	if !test.IsTypeCheck() {
		return engine.CapabilityTest{}
	}
	var alias string
	for _, part := range test.At(1).Match.Descendants() {
		if part.Kind() == "SingleVariableDesignation" {
			alias = part.DeclaredName()
			break
		}
	}
	return engine.CapabilityTest{Subject: test.At(0).Match, Alias: alias}
}
