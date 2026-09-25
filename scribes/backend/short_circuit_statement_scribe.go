package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.ShortCircuitStatementDetector{}, func() scribes.Scribe { return ShortCircuitStatementScribe{} })
}

// ShortCircuitStatementScribe rewrites `$cond && work();` standing alone as a statement into the if it means.
type ShortCircuitStatementScribe struct{}

// Rewrite replaces each such statement with an if, in the file's own brace style.
func (ShortCircuitStatementScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, shortCircuitAsIf, engine.Match.Parent), nil
}

// shortCircuitAsIf is the if a statement short circuit means: the left side as written after an `and`, flipped after
// an `or`, guarding the right side.
func shortCircuitAsIf(finding engine.Match) (string, bool) {
	if !strings.HasPrefix(finding.Kind(), "Expr_BinaryOp_") || finding.Parent().Kind() != "Stmt_Expression" {
		return "", false
	}
	span, err := finding.Span()
	if err != nil {
		return "", false
	}
	indent := span.LineIndent()
	condition := php.Negation(finding.Child("left"))
	if kind := finding.Kind(); kind == "Expr_BinaryOp_BooleanAnd" || kind == "Expr_BinaryOp_LogicalAnd" {
		condition = textOf(finding.Child("left"))
	}
	opener := php.Node{Match: finding}.ControlBlockOpener(indent)

	return "if (" + condition + ")" + opener + "\n" + arm(finding.Child("right"), indent) + ";\n" + indent + "}", true
}
