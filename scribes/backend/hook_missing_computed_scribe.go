package backend

import (
	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

// computed is the attribute that tells Spatie Data a hooked property is computed, not hydrated.
const computed = `Spatie\LaravelData\Attributes\Computed`

func init() {
	scribes.Fixes(spatie.HookMissingComputedDetector{}, func() scribes.Scribe { return HookMissingComputedScribe{} })
}

// HookMissingComputedScribe marks a Data property whose value a get hook computes `#[Computed]`.
type HookMissingComputedScribe struct{}

// Rewrite stamps `#[Computed]` on each hooked property, importing it.
func (HookMissingComputedScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		if property := finding.Parent(); finding.Kind() == "PropertyHook" && property.Kind() == "Stmt_Property" {
			For(draft, finding).StampAttribute(property, "#[Computed]", computed)
		}
	}

	return draft.Rewrites(), nil
}
