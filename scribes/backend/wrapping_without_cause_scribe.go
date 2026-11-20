package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.WrappingWithoutCauseDetector, func() scribes.Scribe { return WrappingWithoutCauseScribe{} })
}

// WrappingWithoutCauseScribe hands the exception a catch caught to the one it throws in its place, as its cause.
type WrappingWithoutCauseScribe struct{}

func (WrappingWithoutCauseScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, throw := range findings {
		finding := throw.Thrown()
		caught := throw.EnclosingCatch().DeclaredName()
		if finding.Kind() != "Expr_New" || caught == "" {
			continue
		}
		span, err := finding.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		cause := "previous: $" + caught
		if len(finding.ChildrenIn("args")) > 0 {
			cause = ", " + cause
		}
		text := span.Text()
		draft.Edit(span, text[:len(text)-1]+cause+")")
	}

	return draft.Rewrites(), nil
}
