package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// EnumCaseOrChainDetector finds an `or` chain testing one subject against case after case of one enum: a membership written as a chain.
type EnumCaseOrChainDetector struct{}

func init() {
	detectors.Register(catalog.Python, EnumCaseOrChainDetector{})
}

func (EnumCaseOrChainDetector) Sin() sins.Sin {
	return pysins.EnumCaseOrChain{}
}

func (EnumCaseOrChainDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("BoolOp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(func(n py.Node) bool { return program.OrChainedCaseClass(n) != "" })).
		Reject(engine.As(py.Node.IsInsideOr)).
		Get()
}
