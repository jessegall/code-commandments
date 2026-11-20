package csharp

import "github.com/jessegall/code-commandments/engine"

func spelledFolder(call engine.Match) (engine.SpelledFolder, bool) {
	if !call.Is(engine.Call) {
		return engine.SpelledFolder{}, false
	}
	target := call.Node().Target
	if target == nil || target.Type != "global::System.IO.Path" || target.Name != "Combine" {
		return engine.SpelledFolder{}, false
	}

	return engine.LiteralPathBelow(".", call.Arguments())
}
