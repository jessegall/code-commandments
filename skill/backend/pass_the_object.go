package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed pass_the_object.intro.md
	passTheObjectIntro string
	//go:embed pass_the_object.principle.md
	passTheObjectPrinciple string
)

// PassTheObject teaches: demand the resolved type you need, not an id plus its container: a method that takes `(Workflow $workflow, string $nodeId)` then unpacks `$workflow->graph->nodeById($nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).
type PassTheObject struct{}

func init() {
	skill.Register(catalog.Backend, PassTheObject{})
}

// Definition is what the skill states about itself.
func (PassTheObject) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/pass-the-object",
		Tier:      skill.KeepInMind,
		Order:     13,
		Title:     "Pass the object, not its id",
		Trigger:   "Demand the resolved type you need, not an id plus its container. When a method takes a container object AND a key into it, then resolves the key against the container (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`), the lookup is misplaced — the caller passed both, so the caller already holds everything the lookup needs. Resolve at the caller and hand over the resolved OBJECT, and let the caller own the \"not found\" failure. Read this when a method signature pairs a domain object with a string/int id it then looks up inside.",
		Intro:     passTheObjectIntro,
		Summary:   "demand the resolved type you need, not an id plus its container: a method that takes `(Workflow $workflow, string $nodeId)` then unpacks `$workflow->graph->nodeById($nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).",
		Principle: passTheObjectPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "the object you pass IS the resolved type — give data a type, don't thread an id + its container."},
			{Slug: "backend/tell-dont-ask", Note: "unpacking a container param to work its target is feature envy on the container."},
		},
	}
}
