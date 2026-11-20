package golang

import "github.com/jessegall/code-commandments/engine"

func spelledFolder(call engine.Match) (engine.SpelledFolder, bool) {
	if !call.Is(engine.Call) {
		return engine.SpelledFolder{}, false
	}
	switch call.TargetSymbol() {
	case "path/filepath.Join", "path.Join":
		return engine.LiteralPathBelow(".", call.ChildrenIn("args"))
	}

	return engine.SpelledFolder{}, false
}
