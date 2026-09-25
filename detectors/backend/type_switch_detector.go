package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// TypeSwitchDetector finds a chain of instanceof branches over the project's own classes: behaviour each class
// should answer itself.
type TypeSwitchDetector struct{}

func init() { detectors.Register(catalog.Backend, TypeSwitchDetector{}) }

// Sin is the sin the detector finds.
func (TypeSwitchDetector) Sin() sins.Sin { return backendsins.TypeSwitch{} }

// Find is the head of every type switch over declared classes, save in a factory building its own class from a
// source and a switch whose every arm translates the subject.
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

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (TypeSwitchDetector) CrossFile() {}
