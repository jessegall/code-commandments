package scope

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestTheLabsOwnShapeExcludesItsTests(t *testing.T) {
	root := t.TempDir()

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	for _, folder := range []string{"src", "tests"} {
		if err := os.MkdirAll(filepath.Join(root, folder), 0o755); err != nil {
			t.Fatal(err)
		}
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for at := range 5 {
		if err := os.WriteFile(filepath.Join(root, "tests", "t"+string(rune('a'+at))+".py"), []byte("y = 2\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	t.Chdir(root)

	held, err := FromArgs([]string{"judge", "--changes", "--no-checklist"}, ".", workspace.At(root, ""), []string{"tests"})
	if err != nil {
		t.Fatal(err)
	}

	if len(held.Files()) != 1 {
		t.Errorf("the scope holds %d files where only src/order.py may be judged; the lab's own run says 401 of "+
			"401, so whatever this test does differently from the real run is the thing still to find", len(held.Files()))

		for file := range held.Files() {
			t.Logf("in scope: %s", file)
		}
	}
}
