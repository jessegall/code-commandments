//go:build story

package roots

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

const rememberedWithin = time.Minute

func TestAWorkersJudgeAppliesTheProjectsConfigToItsOwnWorktreeAndRemembersAtTheProjectRoot(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.SwitchesThePluginSetting("workers_cli", "true", "false")
	memory := s.MemoryFile()
	memory.Remove()

	grace := s.Worker("Grace")
	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Edits(story.ElseAfterRaise, "dist/bundle.py")

	judged := grace.Asks("sins", "--changes", "--no-checklist")

	s.Says(judged, "order.py")
	s.Never(judged, "bundle.py")
	memory.EventuallyHoldsEntries(rememberedWithin)
}

func TestTheMainAgentsJudgeAppliesTheProjectsConfigAtTheProjectRootAndRemembersThere(t *testing.T) {
	s := story.Begin(t)
	memory := s.MemoryFile()
	memory.Remove()

	s.Writes(story.ElseAfterRaise, "order.py")
	s.Writes(story.ElseAfterRaise, "dist/bundle.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes", "--no-checklist")

	s.Says(judged, "order.py")
	s.Never(judged, "bundle.py")
	memory.EventuallyHoldsEntries(rememberedWithin)
}
