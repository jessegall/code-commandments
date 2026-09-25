package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.RedundantEnumUnwrapDetector{}, func() scribes.Scribe { return RedundantEnumUnwrapScribe{} })
}

// RedundantEnumUnwrapScribe hands a Data the enum itself rather than its unwrapped value, the cast rebuilding it.
type RedundantEnumUnwrapScribe struct{}

// Rewrite replaces each finding with the enum it reads the value of.
func (RedundantEnumUnwrapScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, func(finding engine.Match) (string, bool) {
		if kind := finding.Kind(); kind != "Expr_PropertyFetch" && kind != "Expr_NullsafePropertyFetch" {
			return "", false
		}

		return textOf(finding.Child("var")), true
	}, nil), nil
}
