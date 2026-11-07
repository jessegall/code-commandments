package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func TestAJudgingThatFindsNoTreeComingStartsOneRatherThanRefusingForever(t *testing.T) {
	root := t.TempDir()

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"add", "-A"}, {"commit", "-qm", "first"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	nowServing(t)

	if _, _, why := answering(root, root); why != noTreeHeld {
		t.Fatalf("a serving project with no tree says %q, so this test would prove nothing", why)
	}

	if why := WaitsForTheTree(root, root); why != "" {
		t.Fatalf("a judging a person asked for was refused rather than waiting for the tree: %s", why)
	}

	declined := ""

	codebase, err := heldOrDeclined(root, root, &declined)(scan.Walk([]string{filepath.Join(root, "src")}, source.Excluded{}))
	if err != nil {
		t.Fatal(err)
	}

	if declined != "" {
		t.Errorf("the judging after the wait was still refused: %s", declined)
	}

	if len(codebase.Files()) == 0 {
		t.Error("the judging waited and got an empty tree, so it would report a clean project that was never read")
	}
}

func nowServing(t *testing.T) {
	t.Helper()

	was := serving.Load()
	Serves()
	t.Cleanup(func() { serving.Store(was) })
}
