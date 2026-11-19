package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RepeatedQueryChain is the repeated-query-chain sin.
type RepeatedQueryChain struct{}

func init() { sins.Register(catalog.PHP, RepeatedQueryChain{}) }

func (RepeatedQueryChain) Definition() sins.Definition {
	return sins.Definition{
		Name:        "repeated-query-chain",
		Skill:       skills.RepeatedCallHelper{},
		Description: "One Eloquent query chain written alike at three or more sites outside its model",
		Rule:        "A query several callers run is one named finder or scope on the model, written once.",
		Suggestion:  "Add a named static finder or scope to the model (`CommandRequest::lockedFor($id)`) and call it from each site.",
		Requires:    requiresLaravel,
	}
}
