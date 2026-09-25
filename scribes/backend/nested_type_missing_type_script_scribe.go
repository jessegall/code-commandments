package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.NestedTypeMissingTypeScriptDetector{}, func() scribes.Scribe { return NestedTypeMissingTypeScriptScribe{} })
}

// NestedTypeMissingTypeScriptScribe marks the class a field puts on the wire `#[TypeScript]`, where it is declared.
type NestedTypeMissingTypeScriptScribe struct{}

// Rewrite stamps `#[TypeScript]` on each field's nested class, importing it into that class's file.
func (NestedTypeMissingTypeScriptScribe) Rewrite(findings []engine.Match, codebase *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	program := php.ProgramOf(codebase)
	for _, finding := range findings {
		nested := spatie.Node{Match: finding}.NestedWireTypeFqcn()
		if nested == "" {
			continue
		}
		if class, declared := program.Declaration(nested); declared {
			For(draft, class).StampAttribute(class, "#[TypeScript]", typeScript)
		}
	}

	return draft.Rewrites(), nil
}
