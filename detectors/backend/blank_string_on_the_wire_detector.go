package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// BlankStringOnTheWireDetector finds a public string field the frontend tests for blank: the server sends ” where it means absent, and every page decodes it.
type BlankStringOnTheWireDetector struct{}

func init() { detectors.Register(catalog.PHP, BlankStringOnTheWireDetector{}) }

func (BlankStringOnTheWireDetector) Sin() sins.Sin { return backendsins.BlankStringOnTheWire{} }

func (BlankStringOnTheWireDetector) ConsumesContracts() {}

func (BlankStringOnTheWireDetector) Find(codebase *engine.Codebase) []engine.Match {
	questions := published.Of[published.BlanknessQuestion](codebase)

	asked := map[string]bool{}
	for _, question := range questions {
		asked[canonicalField(question.Field)] = true
	}

	return php.In(codebase).
		WhereKind("Param", "Stmt_Property").
		Where(engine.As(php.Node.IsField)).
		Where(func(n engine.Match) bool {
			field, ok := php.AsField(n)

			return ok && asked[canonicalField(field.Name)]
		}).
		Where(func(n engine.Match) bool {
			field, _ := php.AsField(n)
			class := php.EnclosingClassName(n)
			if !field.IsPublic || php.Written(field.Type).Render() != "string" || class == "" {
				return false
			}

			return slices.ContainsFunc(questions, func(question published.BlanknessQuestion) bool {
				return question.AskedOf(php.ShortName(class), field.Name)
			})
		}).
		Get()
}

func (BlankStringOnTheWireDetector) CrossFile() {}

func canonicalField(name string) string {
	return strings.ToLower(strings.NewReplacer("_", "", "-", "").Replace(name))
}
