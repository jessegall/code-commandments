package python

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

// PassTheObject teaches: demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).
type PassTheObject struct{}

func init() {
	skill.Register(catalog.Python, PassTheObject{})
}

// Definition is what the skill states about itself.
func (PassTheObject) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/pass-the-object",
		Tier:      skill.KeepInMind,
		Order:     44,
		Title:     "Python pass the object — demand what you use, not an id and its container",
		Trigger:   "Writing a Python function that takes an object and an id and whose first move is to look one up in the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — or one that takes a value its caller had to convert, derive or compute a bool from first. Read this before adding an `_id` parameter beside the object it keys into, and when a pass-the-object finding points here.",
		Intro:     passTheObjectIntro,
		Summary:   "demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).",
		Principle: passTheObjectPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/pass-the-object", Note: "the same discipline over PHP methods."},
			{Slug: "python/tell-dont-ask", Note: "the sibling: once you hold the object, ask it rather than reaching into it."},
			{Slug: "python/value-objects", Note: "the type an id stands in for."},
		},
	}
}
