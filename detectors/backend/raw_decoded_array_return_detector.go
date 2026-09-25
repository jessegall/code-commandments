package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RawDecodedArrayReturnDetector finds a function handing out json_decode's raw array instead of a type.
type RawDecodedArrayReturnDetector struct{}

func init() { detectors.Register(catalog.Backend, RawDecodedArrayReturnDetector{}) }

// Sin is the sin the detector finds.
func (RawDecodedArrayReturnDetector) Sin() sins.Sin { return backendsins.RawDecodedArrayReturn{} }

// Find is every returned json_decode that does not decode the function's own encoding.
func (RawDecodedArrayReturnDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("json_decode") })).
		Where(engine.As(php.Node.IsReturnedValue)).
		Reject(engine.As(php.Node.DecodesItsOwnEncoding)).
		Get()
}
