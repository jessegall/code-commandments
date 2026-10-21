package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// UnreadCollaboratorDetector finds a collaborator a type takes and never reads.
type UnreadCollaboratorDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, UnreadCollaboratorDetector{})
}

// Sin is the sin the detector finds.
func (UnreadCollaboratorDetector) Sin() sins.Sin {
	return cssins.UnreadCollaborator{}
}

// Find is every collaborator a type takes and never reads.
func (UnreadCollaboratorDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsUnreadCollaborator)).
		Get()
}
