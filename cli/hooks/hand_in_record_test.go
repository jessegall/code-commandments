package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func handInLog(t *testing.T, data string) string {
	t.Helper()

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		t.Fatal(err)
	}

	return string(said)
}

func TestAHandInWhoseTimeRanOutIsRecordedWithHowLongItWaited(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)

	work := merging{worker: "worker 7", worktree: root, window: time.Minute}
	work.records(handIn{got: gotNowhere}.reason(), 0, work.waited(time.Now()))

	said := handInLog(t, data)
	for _, named := range []string{string(gotNowhere), "waited ", "a window of 60000ms"} {
		if !strings.Contains(said, named) {
			t.Errorf("the log does not say %q: %s", named, said)
		}
	}
}

func TestAHandInJudgedInPartIsNotRecordedAsNothingJudged(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)

	merging{worker: "worker 7", worktree: root}.records(handIn{got: withoutTheTree, why: "no tree is held here"}.reason(), 2, "waited 3ms of no limit")

	said := handInLog(t, data)
	for _, named := range []string{string(withoutTheTree), "no tree is held here", "sins=2", "waited 3ms of no limit"} {
		if !strings.Contains(said, named) {
			t.Errorf("the log does not say %q: %s", named, said)
		}
	}
	if strings.Contains(said, string(gotNowhere)) {
		t.Errorf("a hand-in judged in part is logged as judged not at all: %s", said)
	}
}

func TestAHandInJudgedInFullIsRecorded(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)

	merging{worker: "worker 7", worktree: root, window: DeclaredWindow}.answered()

	said := handInLog(t, data)
	for _, named := range []string{deliveringEvent, "tree=held", "waited "} {
		if !strings.Contains(said, named) {
			t.Errorf("the log does not say %q: %s", named, said)
		}
	}
}

func TestALateJudgingIsRecordedEvenWhenItFoundNothing(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)

	answered := make(chan []SinMark, 1)
	answered <- []SinMark{}
	close(answered)

	merging{worker: "worker 7", worktree: root, env: "main", agent: 7}.tellsLater(answered)

	if said := handInLog(t, data); !strings.Contains(said, string(judgedLate)) {
		t.Errorf("the late judging left no line: %s", said)
	}
}
