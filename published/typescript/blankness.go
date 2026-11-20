// Package typescript publishes what the TypeScript a frontend is written in says about the server: which fields of a server type it tests for blank.
package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/published"
)

// Blankness publishes a question for every field the frontend compares with ”, read through a variable typed as one server type.
type Blankness struct{}

func init() {
	published.Register(Blankness{})
}

var equalities = []string{"===", "!==", "==", "!="}

func (Blankness) Contracts(codebase *engine.Codebase) []published.Contract {
	var asked []published.Contract
	seen := map[published.BlanknessQuestion]bool{}
	for _, file := range codebase.Of(contract.TypeScript, contract.Vue).Files() {
		bindings := bindingTypes(file)
		for _, node := range file.Nodes() {
			question := blanknessQuestion(codebase, file.Match(node.ID), bindings)
			if question.Type == "" || seen[question] {
				continue
			}
			seen[question] = true
			asked = append(asked, question)
		}
	}

	return asked
}

func blanknessQuestion(codebase *engine.Codebase, node engine.Match, bindings map[string]string) published.BlanknessQuestion {
	subject, compared := blankComparisonSubject(node)
	if !compared {
		if node.Kind() != "PropertyAccessExpression" || !node.IsRenderConditionPart() {
			return published.BlanknessQuestion{}
		}
		subject = node
	}
	chain, ok := chainOf(subject)
	if !ok || len(chain) != 2 {
		return published.BlanknessQuestion{}
	}
	holder, field := chain[0], chain[1]
	typed := bindings[holder]
	if !compared {
		prop := vue.ComponentOf(subject).Prop(codebase, holder)
		typed = writtenTypeName(prop.Match)
	}

	return published.BlanknessQuestion{Type: typed, Field: field, RequiresBlankDefault: !compared}
}

func blankComparisonSubject(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "BinaryExpression" || !slices.Contains(equalities, node.Node().Operator) {
		return engine.Match{}, false
	}
	left, right := node.Child("left"), node.Child("right")
	if !left.IsBlankString() && !right.IsBlankString() {
		return engine.Match{}, false
	}
	switch {
	case !isLiteral(left) && isLiteral(right):
		return left, true
	case !isLiteral(right) && isLiteral(left):
		return right, true
	}

	return engine.Match{}, false
}

func isLiteral(node engine.Match) bool {
	literal := node.LiteralKind()

	return literal != "" && literal != "interpolated"
}

func chainOf(node engine.Match) ([]string, bool) {
	switch node.Kind() {
	case "Identifier":
		return []string{node.Name()}, true
	case "PropertyAccessExpression":
		base, ok := chainOf(node.Child("expression"))
		if !ok {
			return nil, false
		}

		return append(base, node.Name()), true
	}

	return nil, false
}

func bindingTypes(file *engine.File) map[string]string {
	types, conflicted := map[string]string{}, map[string]bool{}
	for _, node := range file.Nodes() {
		declaration := file.Match(node.ID)
		typed := writtenTypeName(declaration)
		if typed == "" || declaration.Name() == "" {
			continue
		}
		name := declaration.Name()
		if earlier, known := types[name]; known && earlier != typed {
			conflicted[name] = true
		}
		types[name] = typed
	}
	for name := range conflicted {
		delete(types, name)
	}

	return types
}

func writtenTypeName(declaration engine.Match) string {
	if !declaration.Exists() || declaration.Node().Declared == nil {
		return ""
	}
	annotation := declaration.Child("type")
	var names []string
	for _, reference := range append([]engine.Match{annotation}, annotation.Descendants()...) {
		if reference.Kind() == "TypeReference" {
			names = append(names, reference.Child("typeName").Name())
		}
	}
	if len(names) != 1 {
		return ""
	}

	return names[0]
}
