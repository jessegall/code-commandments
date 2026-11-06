package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.TernaryStatementDetector{}, func() scribes.Scribe { return TernaryStatementScribe{} })
}

// TernaryStatementScribe rewrites a ternary standing alone as a statement into the if it means.
type TernaryStatementScribe struct{}

func (TernaryStatementScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, ternaryAsIf, engine.Match.Parent), nil
}

func ternaryAsIf(finding engine.Match) (string, bool) {
	if finding.Kind() != "Expr_Ternary" || finding.Parent().Kind() != "Stmt_Expression" {
		return "", false
	}
	span, err := finding.Span()
	if err != nil {
		return "", false
	}
	indent := span.LineIndent()
	node := php.Node{Match: finding}
	opener := node.ControlBlockOpener(indent)
	if !finding.Child("if").Exists() {
		return "if (" + php.Negation(finding.Child("cond")) + ")" + opener + "\n" + arm(finding.Child("else"), indent) + ";\n" + indent + "}", true
	}
	otherwise := indent + "} else"
	if node.ControlBracesOnOwnLine() {
		otherwise = indent + "}\n" + indent + "else"
	}

	return "if (" + textOf(finding.Child("cond")) + ")" + opener + "\n" + arm(finding.Child("if"), indent) + ";\n" +
		otherwise + opener + "\n" + arm(finding.Child("else"), indent) + ";\n" + indent + "}", true
}

func arm(expression engine.Match, indent string) string {
	span, err := expression.Span()
	if err != nil {
		return ""
	}

	return span.Reindent(indent + "    ")
}

func textOf(node engine.Match) string {
	span, err := node.Span()
	if err != nil {
		return ""
	}

	return span.Text()
}
