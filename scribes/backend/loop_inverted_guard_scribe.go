package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.LoopInvertedGuardDetector{}, func() scribes.Scribe { return LoopInvertedGuardScribe{} })
}

// LoopInvertedGuardScribe turns an if wrapping a loop's whole body into a guard that continues, the body beneath it.
type LoopInvertedGuardScribe struct{}

// Rewrite replaces each wrapping if with its inverted guard and its body, dedented a level.
func (LoopInvertedGuardScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		text, rewrites := invertedGuard(finding)
		if !rewrites {
			continue
		}
		span, err := finding.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		draft.Edit(span, text)
	}

	return draft.Rewrites(), nil
}

// invertedGuard is `if (!cond) continue;` opening its block as this very if does, then the if's body.
func invertedGuard(finding engine.Match) (string, bool) {
	body := finding.ChildrenIn("stmts")
	if finding.Kind() != "Stmt_If" || len(body) == 0 {
		return "", false
	}
	span, err := finding.Span()
	if err != nil {
		return "", false
	}
	indent := span.LineIndent()
	condition := finding.Child("cond")
	opener := engine.Source(span.Source).BlockOpener(condition.Node().Span.End-1, indent)
	lifted := engine.Span{Path: span.Path, Source: span.Source, Start: body[0].Node().Span.Start, End: body[len(body)-1].Node().Span.End}

	return "if (" + php.Negation(condition) + ")" + opener + "\n" + indent + "    continue;\n" + indent + "}\n\n" + lifted.Reindent(indent), true
}
