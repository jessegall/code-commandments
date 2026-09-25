package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.RedundantArrowReturnTypeDetector{}, func() scribes.Scribe { return RedundantArrowReturnTypeScribe{} })
}

// RedundantArrowReturnTypeScribe takes the return type off an arrow function whose body already says it.
type RedundantArrowReturnTypeScribe struct{}

// Rewrite drops each arrow function's return type.
func (RedundantArrowReturnTypeScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		if finding.Kind() == "Expr_ArrowFunction" {
			For(draft, finding).RemoveReturnType(finding)
		}
	}

	return draft.Rewrites(), nil
}
