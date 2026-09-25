package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.RedundantElseDetector{}, func() scribes.Scribe { return RedundantElseScribe{} })
}

// RedundantElseScribe lifts an else's body out beneath the guard that already left.
type RedundantElseScribe struct{}

// Rewrite replaces each if with its guard, the else body following it at the guard's indentation.
func (RedundantElseScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, liftedElse, nil), nil
}

// liftedElse is the guard `if (cond) { … }` verbatim up to its else, and the else's body beneath it dedented a level.
func liftedElse(finding engine.Match) (string, bool) {
	otherwise := finding.Child("else")
	if finding.Kind() != "Stmt_If" || !otherwise.Exists() {
		return "", false
	}
	span, err := finding.Span()
	if err != nil {
		return "", false
	}
	guard := strings.TrimRight(string(span.Source[span.Start:otherwise.Node().Span.Start]), phpSpace)
	body := otherwise.ChildrenIn("stmts")
	if len(body) == 0 {
		return guard, true
	}
	lifted := engine.Span{Path: span.Path, Source: span.Source, Start: body[0].Node().Span.Start, End: body[len(body)-1].Node().Span.End}

	return guard + "\n\n" + lifted.Reindent(span.LineIndent()), true
}
