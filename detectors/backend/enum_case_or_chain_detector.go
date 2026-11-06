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

func init() { detectors.Register(catalog.PHP, EnumCaseOrChainDetector{}) }

func (EnumCaseOrChainDetector) Sin() sins.Sin { return backendsins.EnumCaseOrChain{} }

func (EnumCaseOrChainDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return php.EnumsOf(codebase).IsIndexed(n.OrChainComparedClass()) })).
		Get()
}

func (EnumCaseOrChainDetector) CrossFile() {}
