package backend

import (
	"slices"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(spatie.NonFinalDataDetector{}, func() scribes.Scribe { return NonFinalDataScribe{} })
}

// NonFinalDataScribe seals a Data class: final, and each promoted property readonly.
type NonFinalDataScribe struct{}

// Rewrite writes `final ` before each class keyword and `readonly ` before each sealable promoted parameter.
func (NonFinalDataScribe) Rewrite(findings []engine.Match, codebase *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	program := php.ProgramOf(codebase)
	for _, class := range classDeclarations(findings) {
		writer := For(draft, class)
		if name := class.Child("name"); name.Exists() {
			if keyword, found := writer.Source().Before(name.Node().Span.Start, "class"); found {
				writer.InsertAt(keyword, "final ")
			}
		}
		for _, param := range php.ConstructorParams(class) {
			if !sealable(param, class, program) {
				continue
			}
			typed := param.Child("type")
			if !typed.Exists() {
				typed = param.Child("var")
			}
			writer.InsertAt(typed.Node().Span.Start, "readonly ")
		}
	}

	return draft.Rewrites(), nil
}

// sealable says whether a parameter promotes a writable property no ancestor also declares writable.
func sealable(param, class engine.Match, program *php.Program) bool {
	modifiers := param.Node().Modifiers
	if len(modifiers) == 0 || slices.Contains(modifiers, "readonly") {
		return false
	}
	name := param.Child("var")

	return name.Kind() == "Expr_Variable" && name.Name() != "" && !program.InheritsMutableProperty(php.EnclosingClassName(class), name.Name())
}
