package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ArchaeologyComment is the archaeology-comment sin.
type ArchaeologyComment struct{}

func init() { sins.Register(catalog.Backend, ArchaeologyComment{}) }

// Definition is what the sin states about itself.
func (ArchaeologyComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "archaeology-comment",
		Skill:       skills.Documentation{},
		Description: `History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted")`,
		Rule:        `Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.`,
	}
}
