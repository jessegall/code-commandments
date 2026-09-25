package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// EnumValueMatchDetector finds a match or switch on an enum's ->value outside the enum, per-case knowledge that belongs on the enum.
type EnumValueMatchDetector struct{}

func init() { detectors.Register(catalog.Backend, EnumValueMatchDetector{}) }

// Sin is the sin the detector finds.
func (EnumValueMatchDetector) Sin() sins.Sin { return backendsins.EnumValueMatch{} }

// Find is every match or switch on some ->value that does not sit in an enum.
func (EnumValueMatchDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsMatchOnEnumValue)).
		Reject(engine.As(php.Node.IsInEnum)).
		Get()
}
