package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UnreadCollaboratorDetector finds a private promoted constructor parameter holding a collaborator the class
// never reads.
type UnreadCollaboratorDetector struct{}

func init() { detectors.Register(catalog.Backend, UnreadCollaboratorDetector{}) }

// Sin is the sin the detector finds.
func (UnreadCollaboratorDetector) Sin() sins.Sin { return backendsins.UnreadCollaborator{} }

// Find is every promoted constructor parameter the class keeps private and never reads.
func (UnreadCollaboratorDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Param").
		Where(engine.As(php.Node.IsUnreadCollaborator)).
		Get()
}
