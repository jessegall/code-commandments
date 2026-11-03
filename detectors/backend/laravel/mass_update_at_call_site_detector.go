package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// MassUpdateAtCallSiteDetector finds a model updated with a bare array at the call site, where an intention-revealing method on the model belongs.
type MassUpdateAtCallSiteDetector struct{}

func init() { detectors.Register(catalog.PHP, MassUpdateAtCallSiteDetector{}) }

// Sin is the sin the detector finds.
func (MassUpdateAtCallSiteDetector) Sin() sins.Sin { return backendsins.MassUpdateAtCallSite{} }

// Find is every update() of a model with an array of fields.
func (MassUpdateAtCallSiteDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.MethodCallName() == "update" })).
		Where(engine.As(laravelnode.Node.IsMassArrayUpdate)).
		Where(engine.As(laravelnode.Node.ReceiverIsModel)).
		Get()
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (MassUpdateAtCallSiteDetector) CrossFile() {}
