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

func TestOnlyTheJudgingAliasesAreOpenToWorkers(t *testing.T) {
	s := story.Begin(t)
	grace := s.Worker("Grace")

	judged := s.AsksIn(grace.Folder(), "commandments-judge-changes", "--help")
	rules := s.AsksIn(grace.Folder(), "rules", "schema")

	s.Exits(judged, 0)
	s.Refuses(rules)
}

func TestTheSkillsArePublishedForEveryAgent(t *testing.T) {
	s := story.Begin(t)

	skills := s.Asks("skill", "required")

	s.Exits(skills, 0)
}
