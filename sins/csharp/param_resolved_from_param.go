package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ParamResolvedFromParam is a method that takes a container and a key and first resolves one against the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — when it only wanted what the key names.
type ParamResolvedFromParam struct{}

func init() {
	sins.Register(catalog.CSharp, ParamResolvedFromParam{})
}

// Definition is what the sin states about itself.
func (ParamResolvedFromParam) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-param-resolved-from-param",
		Skill:       skills.PassTheObject{},
		Description: "a method that takes a container and a key and first resolves one against the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — when it only wanted what the key names",
		Rule:        "Take the object the method works on, not an id plus the container it lives in; the caller resolves it once and owns the not-found failure.",
		Suggestion:  "Change the signature to take the resolved object (`Rename(Node node, string title)`) and resolve at the caller, where the id was born.",
	}
}
