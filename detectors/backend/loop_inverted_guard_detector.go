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

func init() { detectors.Register(catalog.PHP, LoopInvertedGuardDetector{}) }

func (LoopInvertedGuardDetector) Sin() sins.Sin { return backendsins.LoopInvertedGuard{} }

func (LoopInvertedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.IsSoleLoopBodyGuard).
		Get()
}

func (LoopInvertedGuardDetector) Repentable() {}
