package scope

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestChangedFilesInAnExcludedFolderAreNeverInScope(t *testing.T) {
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

	for at := range 20 {
		if err := os.WriteFile(filepath.Join(root, "tests", "test_"+string(rune('a'+at))+".py"), []byte("y = 2\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	held, err := FromArgs([]string{"judge", "--changes"}, root, workspace.At(root, ""), []string{"tests"})
	if err != nil {
		t.Fatal(err)
	}

	for file := range held.Files() {
		if filepath.Base(filepath.Dir(file)) == "tests" {
			t.Errorf("%s is in the scope though the settings exclude tests: every run then pays git and the parser "+
				"for a file it can never report, which is how 1451 skipped files became minutes", file)
		}
	}

	if len(held.Files()) != 1 {
		t.Errorf("the scope holds %d files where only src/order.py may be judged", len(held.Files()))
	}
}

func TestAnExclusionHoldsWhenTheRunWasStartedWithARelativePath(t *testing.T) {
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

	if err := os.WriteFile(filepath.Join(root, "tests", "test_one.py"), []byte("y = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	t.Chdir(root)

	held, err := FromArgs([]string{"judge", "--changes"}, ".", workspace.At(root, ""), []string{"tests"})
	if err != nil {
		t.Fatal(err)
	}

	for file := range held.Files() {
		if filepath.Base(filepath.Dir(file)) == "tests" {
			t.Errorf("%s is in scope when the run was started with a relative path, so an agent typing the command "+
				"in its own folder pays for every excluded file while a run given an absolute path does not", file)
		}
	}
}
