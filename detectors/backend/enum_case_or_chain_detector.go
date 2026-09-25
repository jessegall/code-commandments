package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// EnumCaseOrChainDetector finds an || chain comparing one value against several cases of an enum, a question the enum should answer.
type EnumCaseOrChainDetector struct{}

func init() { detectors.Register(catalog.Backend, EnumCaseOrChainDetector{}) }

// Sin is the sin the detector finds.
func (EnumCaseOrChainDetector) Sin() sins.Sin { return backendsins.EnumCaseOrChain{} }

// Find is every outermost || chain comparing against two or more constants of one declared enum.
func (EnumCaseOrChainDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return php.EnumsOf(codebase).IsIndexed(n.OrChainComparedClass()) })).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (EnumCaseOrChainDetector) CrossFile() {}
