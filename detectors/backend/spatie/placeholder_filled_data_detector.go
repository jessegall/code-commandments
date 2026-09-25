package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	spatiesins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// PlaceholderFilledDataDetector finds a Data object built with ” in a slot typed string: a placeholder standing in
// for a value the type says is always there.
type PlaceholderFilledDataDetector struct{}

func init() { detectors.Register(catalog.Backend, PlaceholderFilledDataDetector{}) }

// Sin is the sin the detector finds.
func (PlaceholderFilledDataDetector) Sin() sins.Sin { return spatiesins.PlaceholderFilledData{} }

// Find is every new of a Data class that passes ” to a parameter promising a string.
func (PlaceholderFilledDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Where(engine.As(spatienode.Node.IsNewData)).
		Where(engine.As(func(n php.Node) bool { return fillsAStringSlotWithNothing(codebase, n) })).
		Get()
}

func fillsAStringSlotWithNothing(codebase *engine.Codebase, built php.Node) bool {
	class, _ := php.ProgramOf(codebase).Class(built.NewClassName())
	params := php.ConstructorParams(class)
	for position, argument := range php.Arguments(built.Match) {
		if (php.Node{Match: argument.Child("value")}).IsEmptyString() && php.PromisesScalar(php.ParamForArgument(params, argument, position), "string") {
			return true
		}
	}

	return false
}
