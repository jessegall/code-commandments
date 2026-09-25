package backend

import (
	"strings"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.PreferOptionalCreateDetector{}, func() scribes.Scribe { return PreferOptionalCreateScribe{} })
}

// PreferOptionalCreateScribe builds an Optional through its own `::create()` rather than `new`.
type PreferOptionalCreateScribe struct{}

// Rewrite replaces each `new X()` naming its class with `X::create()`.
func (PreferOptionalCreateScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		class := finding.Child("class")
		if finding.Kind() != "Expr_New" || !strings.HasPrefix(class.Kind(), "Name") {
			continue
		}
		writer := For(draft, finding)
		writer.Replace(finding, writer.TextOf(class)+"::create()")
	}

	return draft.Rewrites(), nil
}
