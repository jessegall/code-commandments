package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// BlankStringDefaultDetector finds a string declaration defaulted to blank that its own scope then tests for blankness: absence spelled as an empty string.
type BlankStringDefaultDetector struct{}

func init() { detectors.Register(catalog.Backend, BlankStringDefaultDetector{}) }

// Sin is the sin the detector finds.
func (BlankStringDefaultDetector) Sin() sins.Sin { return backendsins.BlankStringDefault{} }

// Find is every ” or blank-rendering default of a string parameter or property that its scope tests for blankness.
func (BlankStringDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsBlankString)).
		Where(engine.As(php.Node.IsDeclarationDefault)).
		Where(engine.As(func(n php.Node) bool { return php.Written(n.DeclaredType()).Render() == "string" })).
		Where(engine.As(php.Node.DefaultedNameTestedForBlankness)).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (BlankStringDefaultDetector) WholeTree() {}
