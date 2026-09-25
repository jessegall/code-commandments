package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// NonFinalDataDetector finds a Data class left open for extension that nothing extends.
type NonFinalDataDetector struct{}

func init() { detectors.Register(catalog.Backend, NonFinalDataDetector{}) }

// Sin is the sin the detector finds.
func (NonFinalDataDetector) Sin() sins.Sin { return backendsins.NonFinalData{} }

// Find is every Data class neither final nor abstract that has no subclass.
func (NonFinalDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(php.Node.IsNonFinalClass)).
		Reject(func(n engine.Match) bool { return php.ProgramOf(codebase).HasSubclass(php.EnclosingClassName(n)) }).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (NonFinalDataDetector) Repentable() {}
