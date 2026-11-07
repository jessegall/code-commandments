package scope

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestASkippedFolderCostsNothingHoweverBigItIs(t *testing.T) {
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

	for at := range 300 {
		name := filepath.Join(root, "tests", "test_"+itoa(at)+".py")
		if err := os.WriteFile(name, []byte("y = 2\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	t.Chdir(root)

	held, err := FromArgs([]string{"judge", "--changes"}, ".", workspace.At(root, ""), []string{"tests"})
	if err != nil {
		t.Fatal(err)
	}

	if len(held.Files()) != 1 {
		t.Errorf("the scope holds %d files where one is judged: 300 skipped files are being paid for, in git and "+
			"in the parser, and their findings thrown away. That is what took a project's machine", len(held.Files()))
	}
}

func itoa(number int) string {
	if number == 0 {
		return "0"
	}

	digits := ""
	for number > 0 {
		digits = string(rune('0'+number%10)) + digits
		number /= 10
	}

	return digits
}
