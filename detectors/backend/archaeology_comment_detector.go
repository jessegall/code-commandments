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

// ArchaeologyCommentDetector finds a comment that narrates the code's past instead of what it is now.
type ArchaeologyCommentDetector struct{}

func init() { detectors.Register(catalog.Backend, ArchaeologyCommentDetector{}) }

// Sin is the sin the detector finds.
func (ArchaeologyCommentDetector) Sin() sins.Sin { return backendsins.ArchaeologyComment{} }

// Find is every node with a comment that tells its history.
func (ArchaeologyCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(func(n php.Node) bool { return n.HasCommentMatching(prose.History) })).
		Get()
}
