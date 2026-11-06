package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

const typeScript = `Spatie\TypeScriptTransformer\Attributes\TypeScript`

func init() {
	scribes.Fixes(spatie.PageObjectMissingTypeScriptDetector{}, func() scribes.Scribe { return PageObjectMissingTypeScriptScribe{} })
}

// PageObjectMissingTypeScriptScribe marks a page object `#[TypeScript]`, so the page's props are generated.
type PageObjectMissingTypeScriptScribe struct{}

func (PageObjectMissingTypeScriptScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, class := range classDeclarations(findings) {
		For(draft, class).StampAttribute(class, "#[TypeScript]", typeScript)
	}

	return draft.Rewrites(), nil
}

func classDeclarations(findings []engine.Match) []engine.Match {
	var classes []engine.Match
	for _, finding := range findings {
		if finding.Kind() == "Stmt_Class" {
			classes = append(classes, finding)
		}
	}

	return classes
}
