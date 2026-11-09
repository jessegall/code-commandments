//go:build story

package tools

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

var sinfulFile = story.PlainFile{Path: "src/refund.py", Text: story.ElseAfterRaise.Text}

func TestTheScratchRunnerJudgesAPlainProjectColdWithTheRealBinary(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a sinful python file", sinfulFile)

	ran := project.Judges("src", "--no-checklist")

	s.Says(ran, "this run reads the whole tree itself", "refund.py")
	s.Exits(ran, 1)
}

func TestTheScratchRunnerRunsInAWorktreeOfTheProject(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a sinful python file", sinfulFile)
	worktree := project.Worktree()

	ran := worktree.Judges("src", "--no-checklist")

	s.Says(ran, "this run reads the whole tree itself", "refund.py")
	s.Exits(ran, 1)
}
