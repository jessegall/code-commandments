package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/prose"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NegativeSpaceCommentDetector finds a comment that defends the code against a charge nobody made.
type NegativeSpaceCommentDetector struct{}

func init() { detectors.Register(catalog.Backend, NegativeSpaceCommentDetector{}) }

// Sin is the sin the detector finds.
func (NegativeSpaceCommentDetector) Sin() sins.Sin { return backendsins.NegativeSpaceComment{} }

// Find is every node with a comment that denies a strawman.
func (NegativeSpaceCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return n.HasCommentMatching(prose.Strawman) })).
		Get()
}
