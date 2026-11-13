package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func handInLog(t *testing.T, data string) string {
	t.Helper()

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		t.Fatal(err)
	}

	return string(said)
}

func TestAHandInJudgedInFullIsRecorded(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)

	merging{worker: "worker 7", worktree: root}.answered()

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

	found := merging{worker: "worker 7", worktree: worktree, base: "main", files: []string{"src/older.py"}}.sins()

	if found.got != gotWhole {
		t.Fatalf("the hand-in was not answered from the held tree: %s", found.reason())
	}
	if len(found.marks) != 1 || found.marks[0].Match.Line() != 9 {
		t.Errorf("a hand-in that added one sin at line 9 and left the older one at line 1 reports %d mark(s): %v", len(found.marks), found.marks)
	}
}

func TestAFileInAnotherCheckoutIsAskedAboutInThatCheckout(t *testing.T) {
	root, _ := workerProject(t)
	file := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")

	changed := Event{Root: root}.ChangedLines(file)

	if changed.Covers(1) {
		t.Error("line 1 of the worker's file was not changed, yet it reads as changed")
	}
	if !changed.Covers(2) {
		t.Error("line 2 of the worker's file was changed, yet it reads as untouched")
	}
}

func committed(t *testing.T, root, message string) string {
	t.Helper()

	for _, command := range [][]string{{"add", "-A", "src"}, {"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", message}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	head, err := exec.Command("git", "-C", root, "rev-parse", "HEAD").Output()
	if err != nil {
		t.Fatal(err)
	}

	return strings.TrimSpace(string(head))
}

func TestAMergeIsBlamedOnlyForTheLinesItBroughtIn(t *testing.T) {
	root, _ := workerProject(t)

	older := "def total(order):\n    \"\"\"Says the total.\"\"\"\n    return 0\n\n\nLIMIT = 1\n"
	file := filepath.Join(root, "src", "older.py")

	wroteInto(t, file, older)
	before := committed(t, root, "an older sin")

	wroteInto(t, file, strings.Replace(older, "LIMIT = 1", "LIMIT = 2", 1)+"\n\ndef weight(order):\n    \"\"\"Says the weight.\"\"\"\n    return 0\n")
	after := committed(t, root, "the merge")

	marks, _ := marksBrought(root, []string{file}, before, after)

	if len(marks) != 1 || marks[0].Match.Line() != 9 {
		t.Errorf("a merge that added one sin at line 9 and left the older one at line 1 is blamed for %d mark(s)", len(marks))
	}
}

const reportedSinOnLineOne = "def total(order):\n    \"\"\"Says the total.\"\"\"\n    return 0\n"

func worktreeWithASinTheProjectReported(t *testing.T) (root, worktree string) {
	t.Helper()

	root, _ = workerProject(t)
	worktree = stressWorktreeIn(root, 13)
	cutInto(t, root, worktree, "feature/reported-sin")

	file := filepath.Join(root, "src", "filed.py")
	wroteInto(t, file, reportedSinOnLineOne)
	fileReport(t, root, "DocumentedAnywhereDetector", file, 1)
	wroteInto(t, filepath.Join(worktree, "src", "filed.py"), reportedSinOnLineOne)

	return root, worktree
}

func TestAHandInJudgedWholeLeavesOutASinTheProjectReported(t *testing.T) {
	_, worktree := worktreeWithASinTheProjectReported(t)

	found := merging{worker: "worker 7", worktree: worktree, base: "main", files: []string{"src/filed.py"}}.sins()

	if found.got != gotWhole {
		t.Fatalf("the hand-in was not answered from the held tree: %s", found.reason())
	}
	if len(found.marks) != 0 {
		t.Errorf("a sin the project has reported came back at the worker: %d mark(s)", len(found.marks))
	}
}

func TestAHandInWithNoTreeLeavesOutASinTheProjectReported(t *testing.T) {
	_, worktree := worktreeWithASinTheProjectReported(t)

	found := merging{worker: "worker 7", worktree: worktree, base: "main", files: []string{"src/filed.py"}}.sins()

	if len(found.marks) != 0 {
		t.Errorf("a sin the project has reported came back at the worker: %d mark(s), %s", len(found.marks), found.reason())
	}
}
