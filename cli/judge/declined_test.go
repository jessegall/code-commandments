package judge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

func TestARunThatReadTheWholeTreeSaysWhyWithoutBeingAskedToTime(t *testing.T) {
	root := t.TempDir()

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	t.Setenv(cli.Timing, "")
	t.Chdir(root)

	var out, errs strings.Builder

	(Command{}).Run(cli.InputOf("judge", "--no-checklist"), cli.Console{Out: &out, Err: &errs})

	if !strings.Contains(errs.String(), "reads the whole tree itself") {
		t.Errorf("a run with no service to answer it does not say why it is slow, so a person sees the minutes and "+
			"no reason:\n%s", errs.String())
	}
}
