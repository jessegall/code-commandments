package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// TypeSwitchDetector finds a chain of instanceof branches over the project's own classes: behaviour each class should answer itself.
type TypeSwitchDetector struct{}

func init() { detectors.Register(catalog.PHP, TypeSwitchDetector{}) }

func (TypeSwitchDetector) Sin() sins.Sin { return backendsins.TypeSwitch{} }

func (TypeSwitchDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := php.ProgramOf(codebase)

	return php.In(codebase).
		Where(engine.As(php.Node.IsTypeSwitchHead)).
		Where(engine.As(func(n php.Node) bool {
			for _, class := range n.TypeSwitchClasses() {
				if _, declared := program.Declaration(class); !declared {
					return false
				}
			}

			return true
		})).
		Reject(engine.As(php.Node.IsInFromSourceFactory)).
		Reject(engine.As(php.Node.TypeSwitchTranslatesEveryArm)).
		Get()
}

func (TypeSwitchDetector) CrossFile() {}
