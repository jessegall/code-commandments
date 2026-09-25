package phptypes

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	optionnode "github.com/jessegall/code-commandments/engine/php/phptypes"
	"github.com/jessegall/code-commandments/sins"
	phptypessins "github.com/jessegall/code-commandments/sins/backend/phptypes"
)

// OptionAsNullableDetector finds an Option treated as a nullable: declared ?Option, or unwrapped straight back to
// null.
type OptionAsNullableDetector struct{}

func init() { detectors.Register(catalog.Backend, OptionAsNullableDetector{}) }

// Sin is the sin the detector finds.
func (OptionAsNullableDetector) Sin() sins.Sin { return phptypessins.OptionAsNullable{} }

// Find is every declaration of a nullable Option, and every unwrap to null that does not feed an argument.
func (OptionAsNullableDetector) Find(codebase *engine.Codebase) []engine.Match {
	return append(
		codebase.
			Where(engine.As(optionnode.Node.DeclaresNullableOption)).
			Get(),
		codebase.
			Where(engine.As(optionnode.Node.IsUnwrapOrNull)).
			Reject(engine.As(php.Node.FillsArgument)).
			Get()...,
	)
}
