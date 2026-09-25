package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ParamResolvedFromParam is a function that takes a container and a key and first resolves one against the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — when it only wanted what the key names.
type ParamResolvedFromParam struct{}

func init() {
	sins.Register(catalog.Python, ParamResolvedFromParam{})
}

// Definition is what the sin states about itself.
func (ParamResolvedFromParam) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-param-resolved-from-param",
		Skill:       skills.PassTheObject{},
		Description: "a function that takes a container and a key and first resolves one against the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — when it only wanted what the key names",
		Rule:        "Take the object the function works on, not an id plus the container it lives in; the caller resolves it once and owns the not-found failure.",
		Suggestion:  "Change the signature to take the resolved object (`def rename(node: Node, title: str)`) and resolve at the caller, where the id was born.",
	}
}
