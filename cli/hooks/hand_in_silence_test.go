package hooks

import (
	"strings"
	"testing"
)

func TestAHandInThatWasJudgedInFullSaysNothingExtra(t *testing.T) {
	root, _ := workerProject(t)

	said := merging{worker: "worker 7", worktree: root}.answered()

	if strings.Contains(said, "was not judged in full") {
		t.Errorf("a hand-in that was judged in full carries the words of one that was not: %s", said)
	}
}

func TestHowFarAJudgingGotIsCarriedBesideItsMarks(t *testing.T) {
	root, _ := workerProject(t)

	whole := merging{worker: "worker 7", worktree: root}.sins()

	if whole.got != gotWhole {
		t.Errorf("a judging that finished says it got %q", whole.got)
	}
}
