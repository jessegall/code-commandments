package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UnnamedVocabularyLiteralDetector finds a string literal passed where the same slot is elsewhere spelled with a named constant.
type UnnamedVocabularyLiteralDetector struct{}

func init() { detectors.Register(catalog.Backend, UnnamedVocabularyLiteralDetector{}) }

// Sin is the sin the detector finds.
func (UnnamedVocabularyLiteralDetector) Sin() sins.Sin { return backendsins.UnnamedVocabularyLiteral{} }

// Find is every string literal, not a parameter default, that a class's constant already names for its slot.
func (UnnamedVocabularyLiteralDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Scalar_String").
		Where(func(n engine.Match) bool { return php.VocabularyOf(codebase).NameFor(n) != "" }).
		Reject(engine.As(php.Node.IsParameterDefault)).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (UnnamedVocabularyLiteralDetector) CrossFile() {}
