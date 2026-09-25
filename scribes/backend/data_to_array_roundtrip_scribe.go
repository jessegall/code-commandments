package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.DataToArrayRoundtripDetector{}, func() scribes.Scribe { return DataToArrayRoundtripScribe{} })
}

// DataToArrayRoundtripScribe hands a Data to a from() as itself, not flattened to an array first.
type DataToArrayRoundtripScribe struct{}

// Rewrite replaces each finding with the Data it flattens.
func (DataToArrayRoundtripScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	return replaceEach(findings, func(finding engine.Match) (string, bool) {
		if finding.Kind() != "Expr_MethodCall" {
			return "", false
		}

		return textOf(finding.Child("var")), true
	}, nil), nil
}
