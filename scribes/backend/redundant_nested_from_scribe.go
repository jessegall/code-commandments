package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.RedundantNestedFromDetector{}, func() scribes.Scribe { return RedundantNestedFromScribe{} })
}

// RedundantNestedFromScribe hands a nested Data its array as it is, the parent's from() building it.
type RedundantNestedFromScribe struct{}

// Rewrite replaces each finding with the array it wraps.
func (RedundantNestedFromScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, func(finding engine.Match) (string, bool) {
		arguments := php.Arguments(finding)
		if finding.Kind() != "Expr_StaticCall" || len(arguments) != 1 || arguments[0].Child("value").Kind() != "Expr_Array" {
			return "", false
		}

		return textOf(arguments[0].Child("value")), true
	}, nil), nil
}
