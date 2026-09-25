package backend

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// BlankStringOnTheWireDetector finds a public string field the frontend tests for blank: the server sends ” where
// it means absent, and every page decodes it.
type BlankStringOnTheWireDetector struct{}

func init() { detectors.Register(catalog.Backend, BlankStringOnTheWireDetector{}) }

// Sin is the sin the detector finds.
func (BlankStringOnTheWireDetector) Sin() sins.Sin { return backendsins.BlankStringOnTheWire{} }

// ConsumesContracts says the verdict reads what the frontend publishes about the server's types.
func (BlankStringOnTheWireDetector) ConsumesContracts() {}

// Find is every public string field whose class's short name and field the frontend asks is blank.
func (BlankStringOnTheWireDetector) Find(codebase *engine.Codebase) []engine.Match {
	questions := published.Of[published.BlanknessQuestion](codebase)

	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(func(n engine.Match) bool {
			field, ok := php.AsField(n)
			class := php.EnclosingClassName(n)
			if !ok || !field.IsPublic || php.Written(field.Type).Render() != "string" || class == "" {
				return false
			}

			return slices.ContainsFunc(questions, func(question published.BlanknessQuestion) bool {
				return question.AskedOf(php.ShortName(class), field.Name)
			})
		}).
		Get()
}
