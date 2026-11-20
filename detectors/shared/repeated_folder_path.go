package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

const (
	leastFolderSites  = 3
	leastFolderScopes = 2
)

// RepeatedFolderPath finds one folder spelled out in several scopes.
type RepeatedFolderPath struct{}

func (RepeatedFolderPath) Find(codebase *engine.Codebase) []engine.Match {
	sites := codebase.Where(engine.Match.SpellsAFolder).
		Where(engine.Match.IsWithinFunction).
		Reject(engine.Match.IsTest).
		Get()

	return engine.RepeatedFolders(sites, engine.Match.SpelledFolder, leastFolderSites, leastFolderScopes)
}

func (RepeatedFolderPath) Sin() Text {
	return Text{
		Name:        "repeated-folder-path",
		Skill:       skills.Duplication{},
		Description: "One folder is spelled out at three or more sites across separate scopes",
		Rule:        "Name a folder in one place and share that name wherever code reaches it.",
		Suggestion:  "Put the folder in a shared constant or a method and use it to build paths within that folder.",
	}
}

func (RepeatedFolderPath) CrossFile() {}
