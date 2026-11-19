package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RepeatedFolderPath is the repeated-folder-path sin.
type RepeatedFolderPath struct{}

func init() { sins.Register(catalog.PHP, RepeatedFolderPath{}) }

func (RepeatedFolderPath) Definition() sins.Definition {
	return sins.Definition{
		Name:        "repeated-folder-path",
		Skill:       skills.RepeatedCallHelper{},
		Description: "One folder is spelled as a path at three or more sites in different methods: it needs a single point of entry",
		Rule:        "A folder the code reaches is named in one place, a method or constant that owns it; every other site asks that owner.",
		Suggestion:  "Give the folder one owner (a method that returns its path, or a constant), and have every site that spells the folder or a path inside it call that owner.",
	}
}
