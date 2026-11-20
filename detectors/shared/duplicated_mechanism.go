package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// DuplicatedMechanism finds independent classes that reach the same rare collaborators.
type DuplicatedMechanism struct{}

func (DuplicatedMechanism) Find(codebase *engine.Codebase) []engine.Match {
	return engine.MechanismsOf(codebase).Findings
}

func (DuplicatedMechanism) GroupKey(match engine.Match) (string, bool) {
	codebase := match.Codebase()
	if codebase == nil {
		return "", false
	}
	language, _ := catalog.EngineNamed(string(match.Source().Language()))
	codebase = codebase.Of(catalog.LanguagesOf(language)...)
	key := engine.MechanismsOf(codebase).Keys[match.Location()]

	return key, key != ""
}

func (DuplicatedMechanism) Sin() Text {
	return Text{
		Name:        "duplicated-mechanism",
		Skill:       skills.Duplication{},
		Description: "Independent classes use the same rare collaborators for one job",
		Rule:        "Put a shared mechanism in one place and have both classes use it.",
		Suggestion:  "Extract the common work into a shared class and delete the copies.",
	}
}

func (DuplicatedMechanism) CrossFile() {}

func (DuplicatedMechanism) Unpublished() {}
