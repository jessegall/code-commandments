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

func init() { detectors.Register(catalog.PHP, GenericExceptionDetector{}) }

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

func (GenericExceptionDetector) Sin() sins.Sin { return backendsins.GenericException{} }

func (GenericExceptionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return slices.Contains(generic, n.NewClassName()) })).
		Where(engine.As(func(n php.Node) bool { return n.Up().IsThrow() })).
		Get()
}
