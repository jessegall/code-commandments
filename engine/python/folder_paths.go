package python

import "github.com/jessegall/code-commandments/engine"

func spelledFolder(call engine.Match) (engine.SpelledFolder, bool) {
	if !call.Is(engine.Call) {
		return engine.SpelledFolder{}, false
	}
	parts := call.ChildrenIn("args")
	if len(parts) == 0 || !parts[0].Is(engine.Literal) {
		return engine.SpelledFolder{}, false
	}
	program := Of(call.Codebase())
	module := program.ModuleOf(Node{Match: call})
	if module == nil {
		return engine.SpelledFolder{}, false
	}
	name, imported := program.outsideName(Node{Match: call.Child("func")}, Node{Match: call.EnclosingFunction()}, module)
	if !imported || name != "os.path.join" {
		return engine.SpelledFolder{}, false
	}

	return engine.LiteralPathBelow(".", parts)
}
