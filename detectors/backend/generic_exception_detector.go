package backend

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// GenericExceptionDetector finds a built-in exception thrown where a named one belongs.
type GenericExceptionDetector struct{}

func init() { detectors.Register(catalog.Backend, GenericExceptionDetector{}) }

// generic are the built-in exceptions that say nothing about what went wrong.
var generic = []string{
	"Exception",
	"Error",
	"RuntimeException",
	"LogicException",
	"InvalidArgumentException",
	"DomainException",
	"UnexpectedValueException",
	"OutOfRangeException",
	"OutOfBoundsException",
	"RangeException",
	"LengthException",
}

// Sin is the sin the detector finds.
func (GenericExceptionDetector) Sin() sins.Sin { return backendsins.GenericException{} }

// Find is every new of a built-in exception that a throw throws.
func (GenericExceptionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return slices.Contains(generic, n.NewClassName()) })).
		Where(engine.As(func(n php.Node) bool { return n.Up().IsThrow() })).
		Get()
}
