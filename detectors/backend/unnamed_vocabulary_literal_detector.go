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

func init() { detectors.Register(catalog.PHP, UnnamedVocabularyLiteralDetector{}) }

func (UnnamedVocabularyLiteralDetector) Sin() sins.Sin { return backendsins.UnnamedVocabularyLiteral{} }

func (UnnamedVocabularyLiteralDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Scalar_String").
		Where(func(n engine.Match) bool { return php.VocabularyOf(codebase).NameFor(n) != "" }).
		Reject(engine.As(php.Node.IsParameterDefault)).
		Get()
}

func (UnnamedVocabularyLiteralDetector) CrossFile() {}
