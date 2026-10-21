package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// UnreadCollaboratorDetector finds a collaborator __init__ keeps under a name of its own that nothing reads.
type UnreadCollaboratorDetector struct{}

func init() {
	detectors.Register(catalog.Python, UnreadCollaboratorDetector{})
}

// Sin is the sin the detector finds.
func (UnreadCollaboratorDetector) Sin() sins.Sin {
	return pysins.UnreadCollaborator{}
}

// Find is every collaborator an __init__ keeps and nothing reads.
func (UnreadCollaboratorDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereAssign().
		Where(engine.As(func(n py.Node) bool { return n.IsUnreadCollaborator(codebase) })).
		Get()
}
