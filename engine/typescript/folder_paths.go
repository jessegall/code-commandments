package typescript

import (
	"path"

	"github.com/jessegall/code-commandments/engine"
)

func SpelledFolder(call engine.Match) (engine.SpelledFolder, bool) {
	if call.Is(engine.Construction) {
		return spelledURLFolder(call)
	}
	if !call.Is(engine.Call) {
		return engine.SpelledFolder{}, false
	}
	arguments := call.Arguments()
	if len(arguments) < 2 || arguments[0].Kind() != "Identifier" || arguments[0].Name() != "__dirname" {
		return engine.SpelledFolder{}, false
	}
	if !isPathCall(call) {
		return engine.SpelledFolder{}, false
	}
	return engine.LiteralPathBelow(path.Dir(call.File()), arguments[1:])
}

func spelledURLFolder(construction engine.Match) (engine.SpelledFolder, bool) {
	name := construction.Child("expression")
	if name.Kind() != "Identifier" || name.Name() != "URL" {
		return engine.SpelledFolder{}, false
	}
	arguments := construction.Arguments()
	if len(arguments) != 2 {
		return engine.SpelledFolder{}, false
	}
	base := arguments[1]
	if base.Kind() != "PropertyAccessExpression" || base.Child("name").Name() != "url" {
		return engine.SpelledFolder{}, false
	}
	if receiver := base.Child("expression"); receiver.Kind() != "MetaProperty" || receiver.Child("name").Name() != "meta" {
		return engine.SpelledFolder{}, false
	}

	return engine.LiteralPathBelow(path.Dir(construction.File()), arguments[:1])
}

func isPathCall(call engine.Match) bool {
	callee := call.Child("expression")
	var name string
	switch callee.Kind() {
	case "Identifier":
		name = callee.Name()
	case "PropertyAccessExpression":
		if receiver := callee.Child("expression"); receiver.Kind() == "Identifier" {
			name = receiver.Name()
		}
	}
	if name == "" {
		return false
	}
	for source := call.Parent(); source.Exists(); source = source.Parent() {
		if source.Kind() != "SourceFile" {
			continue
		}
		module := ModuleOf(source.ChildrenIn("statements"))
		imported, ok := module.ImportOf(name)
		if !ok || (imported.Specifier != "node:path" && imported.Specifier != "path") {
			return false
		}
		member := imported.Bindings[name]
		if callee.Kind() == "PropertyAccessExpression" {
			if member != "*" && member != "default" {
				return false
			}
			member = callee.Child("name").Name()
		}

		return member == "join" || member == "resolve"
	}

	return false
}
