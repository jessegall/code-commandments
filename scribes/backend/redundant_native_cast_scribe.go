package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.RedundantNativeCastDetector{}, func() scribes.Scribe { return RedundantNativeCastScribe{} })
}

// RedundantNativeCastScribe hands a Data the raw value a cast it declares already converts.
type RedundantNativeCastScribe struct{}

// Rewrite replaces each finding with the one value it converts.
func (RedundantNativeCastScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, func(finding engine.Match) (string, bool) {
		arguments := php.Arguments(finding)
		if kind := finding.Kind(); kind != "Expr_StaticCall" && kind != "Expr_New" || len(arguments) != 1 {
			return "", false
		}

		return textOf(arguments[0].Child("value")), true
	}, nil), nil
}
