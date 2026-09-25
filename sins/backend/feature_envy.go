package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// FeatureEnvy is the feature-envy sin.
type FeatureEnvy struct{}

func init() { sins.Register(catalog.Backend, FeatureEnvy{}) }

// Definition is what the sin states about itself.
func (FeatureEnvy) Definition() sins.Definition {
	return sins.Definition{
		Name:        "feature-envy",
		Skill:       skills.TellDontAsk{},
		Description: `Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object`,
		Rule:        `Behaviour belongs with its data — move a method that loops or queries one other owned object onto that object.`,
		Suggestion:  "Move the method onto the object (`$node->edges()`).",
	}
}
