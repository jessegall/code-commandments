package hooks

import (
	"strings"
	"testing"
	"time"
)

func TestAHandInNobodyCouldJudgeSaysSo(t *testing.T) {
	root, _ := workerProject(t)

	for _, told := range []struct {
		what   string
		work   merging
		words  []string
		absent []string
	}{
		{
			"a hand-in whose time ran out before anything was read",
			merging{worker: "worker 7", worktree: root, window: time.Nanosecond, limited: true},
			[]string{"was not judged in full", "nothing of this hand-in was judged", "--changes"},
			nil,
		},
	} {
		said := told.work.answered()

		for _, names := range told.words {
			if !strings.Contains(said, names) {
				t.Errorf("%s does not say %q: %s", told.what, names, said)
			}
		}

		if strings.Contains(said, `"refuse"`) {
			t.Errorf("%s was refused, and a refusal from a listen or commit script is a fault: %s", told.what, said)
		}

		if said == "" {
			t.Errorf("%s was answered with silence, which the journal reads as no objection", told.what)
		}
	}
}

func TestAHandInThatWasJudgedInFullSaysNothingExtra(t *testing.T) {
	root, _ := workerProject(t)

	said := merging{worker: "worker 7", worktree: root, window: DeclaredWindow, limited: true}.answered()

	if strings.Contains(said, "was not judged in full") {
		t.Errorf("a hand-in that was judged in full carries the words of one that was not: %s", said)
	}
}

func TestHowFarAJudgingGotIsCarriedBesideItsMarks(t *testing.T) {
	root, _ := workerProject(t)

	nowhere := merging{worker: "worker 7", worktree: root, window: time.Nanosecond, limited: true}.sinsWithin()

	if nowhere.got != gotNowhere {
		t.Errorf("a judging whose time ran out at once says it got %q", nowhere.got)
	}

	if len(nowhere.marks) > 0 {
		t.Errorf("a judging that got nowhere carries %d mark(s)", len(nowhere.marks))
	}

	whole := merging{worker: "worker 7", worktree: root, window: DeclaredWindow, limited: true}.sinsWithin()

	if whole.got != gotWhole {
		t.Errorf("a judging that finished says it got %q", whole.got)
	}
}
