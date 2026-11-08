//go:build story

package install

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

func TestTheHooksServiceIsListedAmongTheInstalledPlugin(t *testing.T) {
	s := story.Begin(t)

	listed := s.Asks("plugin", "list")

	s.Says(listed, "code-commandments")
}

func TestTheAliasesAppearWithTheWordsTheyTake(t *testing.T) {
	s := story.Begin(t)

	aliases := s.Asks("alias", "list")

	s.Says(aliases, "sins", "commandments-judge-changes", "rules", "commandments")
}

func TestTheJudgingAliasIsOpenToWorkers(t *testing.T) {
	s := story.Begin(t)
	grace := s.Worker("Grace")

	s.Exits(grace.Asks("commandments-judge-changes", "--help"), 0)
}

func TestTheSkillsArePublishedForEveryAgent(t *testing.T) {
	s := story.Begin(t)

	skills := s.Asks("skill", "required")

	s.Exits(skills, 0)
}
