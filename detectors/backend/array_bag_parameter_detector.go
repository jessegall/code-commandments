package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ArrayBagParameterDetector finds a parameter declared `array` that its callers fill with string-keyed records.
type ArrayBagParameterDetector struct{}

func init() { detectors.Register(catalog.PHP, ArrayBagParameterDetector{}) }

func (ArrayBagParameterDetector) Sin() sins.Sin { return backendsins.ArrayBagParameter{} }

func (ArrayBagParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Param").
		Where(engine.As(php.Node.IsArrayParameter)).
		Where(engine.As(php.Node.IsFilledWithRecords)).
		Reject(engine.As(php.Node.IsWithinInheritedMethod)).
		Reject(engine.Match.IsTest).
		Get()
}

func (ArrayBagParameterDetector) CrossFile() {}
