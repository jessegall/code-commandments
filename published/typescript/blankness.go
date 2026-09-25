// Package typescript publishes what the TypeScript a frontend is written in says about the server: which fields
// of a server type it tests for blank.
package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/published"
)

// Blankness publishes a question for every field the frontend compares with ”, read through a variable typed as
// one server type.
type Blankness struct{}

func init() {
	published.Register(Blankness{})
}

// equalities are the operators that test a value against another.
var equalities = []string{"===", "!==", "==", "!="}

// Contracts is each field of a typed holder some expression compares with ”, once per type and field.
func (Blankness) Contracts(codebase *engine.Codebase) []published.Contract {
	var asked []published.Contract
	seen := map[[2]string]bool{}
	for _, file := range codebase.Of(contract.TypeScript, contract.Vue).Files() {
		bindings := bindingTypes(file)
		for _, node := range file.Nodes() {
			comparison := file.Match(node.ID)
			subject, ok := blankComparisonSubject(comparison)
			if !ok {
				continue
			}
			chain, ok := chainOf(subject)
			if !ok || len(chain) != 2 {
				continue
			}
			holder, field := chain[0], chain[1]
			if typed := bindings[holder]; typed != "" && !seen[[2]string{typed, field}] {
				seen[[2]string{typed, field}] = true
				asked = append(asked, published.BlanknessQuestion{Type: typed, Field: field})
			}
		}
	}

	return asked
}

// blankComparisonSubject is what an equality test compares with ”: the side that is no literal, when the other is.
func blankComparisonSubject(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "BinaryExpression" || !slices.Contains(equalities, node.Node().Operator) {
		return engine.Match{}, false
	}
	left, right := node.Child("left"), node.Child("right")
	if !isBlankLiteral(left) && !isBlankLiteral(right) {
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
	literal := node.Node().Literal

	return node.Exists() && literal != "" && literal != "interpolated"
}

func isBlankLiteral(node engine.Match) bool {
	text, ok := node.Text()

	return node.Exists() && node.Node().Literal == "string" && ok && text == ""
}

// chainOf is a pure member chain's names, root first; false for anything else.
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

// bindingTypes is the type each name of the file is annotated with, when its annotation names exactly one type and
// no two annotations of the name disagree.
func bindingTypes(file *engine.File) map[string]string {
	types, conflicted := map[string]string{}, map[string]bool{}
	for _, node := range file.Nodes() {
		declaration := file.Match(node.ID)
		annotation := declaration.Child("type")
		if node.Declared == nil || !annotation.Exists() {
			continue
		}
		var named []string
		for _, reference := range append([]engine.Match{annotation}, annotation.Descendants()...) {
			if reference.Kind() == "TypeReference" {
				named = append(named, reference.Child("typeName").Name())
			}
		}
		if len(named) != 1 || declaration.Name() == "" {
			continue
		}
		name := declaration.Name()
		if earlier, known := types[name]; known && earlier != named[0] {
			conflicted[name] = true
		}
		types[name] = named[0]
	}
	for name := range conflicted {
		delete(types, name)
	}

	return types
}
