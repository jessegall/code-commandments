package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
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

func TestAHandInOnAHeldTreeReportsOnlyTheLinesItChanged(t *testing.T) {
	root, _ := workerProject(t)

	older := "def total(order):\n    \"\"\"Says the total.\"\"\"\n    return 0\n\n\nLIMIT = 1\n"
	wroteInto(t, filepath.Join(root, "src", "older.py"), older)

	for _, command := range [][]string{{"add", "-A"}, {"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "an older sin"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	worktree := stressWorktreeIn(root, 11)
	cutInto(t, root, worktree, "feature/held-hand-in")

	wroteInto(t, filepath.Join(worktree, "src", "older.py"), strings.Replace(older, "LIMIT = 1", "LIMIT = 2", 1)+
		"\n\ndef weight(order):\n    \"\"\"Says the weight.\"\"\"\n    return 0\n")

	settings, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	warmFor(t, root, settings)

	found := merging{worker: "worker 7", worktree: worktree, base: "main", files: []string{"src/older.py"}, window: DeclaredWindow}.sinsWithin()

	if found.got != gotWhole {
		t.Fatalf("the hand-in was not answered from the held tree: %s", found.reason())
	}
	if len(found.marks) != 1 || found.marks[0].Match.Line() != 9 {
		t.Errorf("a hand-in that added one sin at line 9 and left the older one at line 1 reports %d mark(s): %v", len(found.marks), found.marks)
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
