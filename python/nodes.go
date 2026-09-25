package python

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// IsDefinition says whether the node is a def, an async def or a class.
func IsDefinition(node engine.Match) bool {
	return IsFunction(node) || node.Kind() == "ClassDef"
}

// IsFunction says whether the node is a def or an async def; a lambda is not one.
func IsFunction(node engine.Match) bool {
	return node.Kind() == "FunctionDef" || node.Kind() == "AsyncFunctionDef"
}

// IsImport says whether the node is an import or a from-import.
func IsImport(node engine.Match) bool {
	return node.Kind() == "Import" || node.Kind() == "ImportFrom"
}

// Level is a from-import's relative dot count: 0 for an absolute import.
func Level(node engine.Match) int {
	if extras := node.Node().Extras; extras != nil && extras.Python != nil {
		return extras.Python.Level
	}

	return 0
}

// boundAs is the name an import alias binds: its rename, else the first part of what it imports.
func boundAs(alias engine.Match) string {
	if renamed, ok := renamedTo(alias); ok {
		return renamed
	}
	if alias.Parent().Kind() == "Import" {
		return strings.Split(alias.Name(), ".")[0]
	}

	return alias.Name()
}

// DottedName is a name or attribute chain spelled out, a.b.c; empty for any other expression.
func DottedName(expression engine.Match) string {
	switch expression.Kind() {
	case "Name":
		return expression.Name()
	case "Attribute":
		owner := DottedName(expression.Child("value"))
		if owner == "" {
			return ""
		}

		return owner + "." + expression.Name()
	}

	return ""
}

// EnclosingFunction is the nearest def around the node, the node itself when it is one; a lambda is passed over.
func EnclosingFunction(node engine.Match) engine.Match {
	for around := node; around.Exists(); around = around.Parent() {
		if IsFunction(around) {
			return around
		}
	}

	return engine.Match{}
}

// insideDefinition says whether a def or a class holds the node.
func insideDefinition(node engine.Match) bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if IsDefinition(around) {
			return true
		}
	}

	return false
}

// declaredNames are the names a statement declares: a definition's own, an import's bindings.
func declaredNames(statement engine.Match) []string {
	if IsDefinition(statement) {
		return []string{statement.Name()}
	}
	if !IsImport(statement) {
		return nil
	}
	var names []string
	for _, alias := range statement.ChildrenIn("names") {
		names = append(names, boundAs(alias))
	}

	return names
}

// writtenTargets are the expressions an assignment writes: every target of an =, an augmented one's target, an
// annotated one's target when it holds a value.
func writtenTargets(statement engine.Match) []engine.Match {
	switch statement.Kind() {
	case "Assign":
		return statement.ChildrenIn("targets")
	case "AugAssign":
		return []engine.Match{statement.Child("target")}
	case "AnnAssign":
		if statement.Child("value").Exists() {
			return []engine.Match{statement.Child("target")}
		}
	}

	return nil
}

// writtenNames are the dotted names an assignment writes.
func writtenNames(statement engine.Match) []string {
	var names []string
	for _, target := range writtenTargets(statement) {
		names = append(names, DottedName(target))
	}

	return names
}
