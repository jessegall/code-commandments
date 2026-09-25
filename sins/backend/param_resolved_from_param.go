package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ParamResolvedFromParam is the param-resolved-from-param sin.
type ParamResolvedFromParam struct{}

func init() { sins.Register(catalog.Backend, ParamResolvedFromParam{}) }

// Definition is what the sin states about itself.
func (ParamResolvedFromParam) Definition() sins.Definition {
	return sins.Definition{
		Name:        "param-resolved-from-param",
		Skill:       skills.PassTheObject{},
		Description: `Unpacking the target out of a container parameter — a method takes ` + "`" + `(Workflow $workflow, string $nodeId)` + "`" + ` and resolves ` + "`" + `$workflow->graph->nodeById($nodeId)` + "`" + ` itself, when it could just receive the node directly.`,
		Rule:        `Demand the resolved object you need; don't take a container + key and unpack the target yourself — the caller resolves once and passes it.`,
		Suggestion:  "Take the resolved object as the param; resolve once in the caller.",
	}
}
