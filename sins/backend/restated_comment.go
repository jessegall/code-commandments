package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RestatedComment is the restated-comment sin.
type RestatedComment struct{}

func init() { sins.Register(catalog.Backend, RestatedComment{}) }

// Definition is what the sin states about itself.
func (RestatedComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "restated-comment",
		Skill:       skills.Documentation{},
		Description: `An inline comment that only spells the statement below it back in prose ("// save the order" over ` + "`" + `$this->orders->save($order)` + "`" + `)`,
		Rule:        `An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.`,
	}
}
