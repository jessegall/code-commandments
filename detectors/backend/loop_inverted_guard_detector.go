package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// LoopInvertedGuardDetector finds a loop whose whole body sits inside one if, where an early continue belongs.
type LoopInvertedGuardDetector struct{}

func init() { detectors.Register(catalog.Backend, LoopInvertedGuardDetector{}) }

// Sin is the sin the detector finds.
func (LoopInvertedGuardDetector) Sin() sins.Sin { return backendsins.LoopInvertedGuard{} }

// Find is every if that is its loop's only statement and guards two or more of its own.
func (LoopInvertedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsSoleLoopBodyGuard)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (LoopInvertedGuardDetector) Repentable() {}
