package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

// olderSin is a Python module holding one sin a worker did not write, and room to add another.
const olderSin = `def refund(order) -> int:
    if order.late:
        raise ValueError(order)
    else:
        return order.total
`

// workerEdit is that module with a second sin added at the end, which is the only part the worker wrote.
const workerEdit = olderSin + `

def ship(order) -> int:
    if order.packed:
        raise ValueError(order)
    else:
        return order.weight
`

// committedProject is a repository holding the older sin on its branch, and the path of the file holding it.
func committedProject(t *testing.T) (root, file string) {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Setenv(pluginData, t.TempDir())
	file = filepath.Join(root, "src", "refund.py")
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	for path, text := range map[string]string{".commandments/config.json": `{"paths": ["src"]}`, "src/refund.py": olderSin} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	for _, args := range [][]string{{"init", "-q"}, {"add", "-A"},
		{"-c", "user.email=a@b", "-c", "user.name=a", "commit", "-q", "-m", "the sin that was already here"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", args, out)
		}
	}

	return root, file
}

// TestAnEditIsMarkedOnlyWhereItLanded is the question Jesse's request turns on: a file already held a sin, and
// something edited that file elsewhere. The mark is about what the edit did, so the older sin is not it.
func TestAnEditIsMarkedOnlyWhereItLanded(t *testing.T) {
	root, file := committedProject(t)
	if err := os.WriteFile(file, []byte(workerEdit), 0o644); err != nil {
		t.Fatal(err)
	}

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}
	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}
	event := NewEvent(editOf("src/refund.py"), root)
	marks := marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), event.ChangedLines(file))
	if len(marks) == 0 {
		t.Fatal("an edit that wrote a sin raises no mark at all")
	}

	for _, mark := range marks {
		if !mark.Touched {
			continue
		}
		if line := mark.Match.Line(); line < 7 {
			t.Errorf("the mark at line %d is the sin that was already there, which this edit did not write", line)
		}
	}
	var touched int
	for _, mark := range marks {
		if mark.Touched {
			touched++
		}
	}
	if touched != 1 {
		t.Errorf("%d marks read as touched, where the edit wrote one sin", touched)
	}
	t.Logf("marks: %d, of which touched: %d", len(marks), touched)
	for _, mark := range marks {
		t.Logf("  line %d touched=%v %s", mark.Match.Line(), mark.Touched, strings.TrimSpace(mark.Found()))
	}
}

// TestTheJudgeOfChangedLinesAgreesWithTheEditCheck is the parity Jesse asked for by name. Two readings of one
// diff must say the same thing: the lines the file-edit check calls touched, and the lines `judge --changes`
// reports on. They are computed by different code for different purposes, and a project told two different
// stories about its own change has no idea which to believe.
func TestTheJudgeOfChangedLinesAgreesWithTheEditCheck(t *testing.T) {
	root, file := committedProject(t)
	if err := os.WriteFile(file, []byte(workerEdit), 0o644); err != nil {
		t.Fatal(err)
	}

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}
	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}
	event := NewEvent(editOf("src/refund.py"), root)

	var touched []int
	for _, mark := range marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), event.ChangedLines(file)) {
		if mark.Touched {
			touched = append(touched, mark.Match.Line())
		}
	}

	scoped, err := scope.FromArgs([]string{"--changes"}, root, event.Workspace(), nil)
	if err != nil {
		t.Fatal(err)
	}

	for _, line := range touched {
		if !scoped.Covers(file, line) {
			t.Errorf("the edit check calls line %d touched and judge --changes does not report it", line)
		}
	}
	if len(touched) == 0 {
		t.Fatal("the edit check found nothing touched, so the two readings are not being compared at all")
	}

	// And the other way: a sin the edit check leaves alone is one the judge leaves alone.
	if scoped.Covers(file, 2) {
		t.Error("judge --changes reports line 2, which the edit check does not call touched")
	}
}
