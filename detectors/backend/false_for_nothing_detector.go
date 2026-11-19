package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// FalseForNothingDetector finds the false-for-nothing sin.
type FalseForNothingDetector struct{}

func init() { detectors.Register(catalog.PHP, FalseForNothingDetector{}) }

func (FalseForNothingDetector) Sin() sins.Sin { return backendsins.FalseForNothing{} }

func (FalseForNothingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.ReturnsFalseForNothing)).
		Reject(engine.As(php.Node.IsWithinInheritedMethod)).
		Reject(engine.Match.IsTest).
		Get()
}
