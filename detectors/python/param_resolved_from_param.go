package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ParamResolvedFromParamDetector finds a def that resolves a key parameter against a container parameter used for nothing else: take the resolved object.
type ParamResolvedFromParamDetector struct{}

func init() {
	detectors.Register(catalog.Python, ParamResolvedFromParamDetector{})
}

// Sin is the sin the detector finds.
func (ParamResolvedFromParamDetector) Sin() sins.Sin {
	return pysins.ParamResolvedFromParam{}
}

// Find is every place the sin is committed.
func (ParamResolvedFromParamDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereFunction().
		Where(engine.As(program.UnpacksTargetFromContainerParam)).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (ParamResolvedFromParamDetector) CrossFile() {}
