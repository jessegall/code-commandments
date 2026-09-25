package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.NestedTernaryDetector{}, func() scribes.Scribe { return NestedTernaryScribe{} })
}

// NestedTernaryScribe rewrites a chain of ternaries nested in their else branches into one `match (true)`.
type NestedTernaryScribe struct{}

// Rewrite replaces each flat else-chain with its match.
func (NestedTernaryScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		if text, rewrites := ternaryChainAsMatch(finding); rewrites {
			span, err := finding.Span()
			if err != nil {
				return scribes.Rewrites{}, err
			}
			draft.Edit(span, text)
		}
	}

	return draft.Rewrites(), nil
}

// ternaryChainAsMatch is the match (true) an else-chain of ternaries means, one arm per ternary and its last else the
// default; a short ternary, or one buried in a condition or a then branch, is no flat chain.
func ternaryChainAsMatch(finding engine.Match) (string, bool) {
	if finding.Kind() != "Expr_Ternary" {
		return "", false
	}
	var arms []string
	node := finding
	for node.Kind() == "Expr_Ternary" {
		if !node.Child("if").Exists() || holdsATernary(node.Child("cond")) || holdsATernary(node.Child("if")) {
			return "", false
		}
		arms = append(arms, textOf(node.Child("cond"))+" => "+textOf(node.Child("if")))
		node = node.Child("else")
	}
	arms = append(arms, "default => "+textOf(node))

	span, err := finding.Span()
	if err != nil {
		return "", false
	}
	line := string(span.Source[engine.Source(span.Source).LineStartAt(span.Start):])
	indent := line[:len(line)-len(strings.TrimLeft(line, " \t"))]
	var body strings.Builder
	for _, arm := range arms {
		body.WriteString(indent + "    " + arm + ",\n")
	}

	return "match (true) {\n" + body.String() + indent + "}", true
}

// holdsATernary says whether the node is a ternary or has one anywhere beneath it.
func holdsATernary(node engine.Match) bool {
	if node.Kind() == "Expr_Ternary" {
		return true
	}
	for _, descendant := range node.Descendants() {
		if descendant.Kind() == "Expr_Ternary" {
			return true
		}
	}

	return false
}
