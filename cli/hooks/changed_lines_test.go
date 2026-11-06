package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

const olderSin = `def refund(order) -> int:
    if order.late:
        raise ValueError(order)
    else:
        return order.total
`

const workerEdit = olderSin + `

def ship(order) -> int:
    if order.packed:
        raise ValueError(order)
    else:
        return order.weight
`

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
	marks, _, _ := marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), event.ChangedLines(file))
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
	found, _, _ := marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), event.ChangedLines(file))

	for _, mark := range found {
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

	if scoped.Covers(file, 2) {
		t.Error("judge --changes reports line 2, which the edit check does not call touched")
	}
}

func TestAMomentsLinesAreDroppedWhereItsFileMovedAgain(t *testing.T) {
	root := t.TempDir()

	file := filepath.Join(root, "order.py")
	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	carried := map[string]any{
		"hook_event_name": "PostToolUse",
		"tool_name":       "Edit",
		"tool_input":      map[string]any{"file_path": file},
	}
	carried[changedKey] = map[string]any{"lines": []any{float64(2)}}
	carried = Stamped(carried)

	if lines := NewEvent(carried, root).ChangedLines(file); !lines.Covers(2) || lines.Covers(5) {
		t.Error("the moment's own lines are not what the mark reads while the file stands as the moment left it")
	}

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("x = 1\ny = 2\nz = 3\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	lines := NewEvent(carried, root).ChangedLines(file)
	if !lines.Covers(5) {
		t.Error("a moment whose file moved again is still marked with the lines of the version that is gone")
	}
}

func TestAMomentsLinesStandWhenOnlyTheClocksDisagree(t *testing.T) {
	root := t.TempDir()

	file := filepath.Join(root, "order.py")
	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	carried := map[string]any{
		"hook_event_name": "PostToolUse",
		"tool_name":       "Edit",
		"tool_input":      map[string]any{"file_path": file},
	}
	carried[changedKey] = map[string]any{"lines": []any{float64(2)}}

	ahead := time.Now().Add(time.Minute)
	if err := os.Chtimes(file, ahead, ahead); err != nil {
		t.Fatal(err)
	}

	carried = Stamped(carried)

	if lines := NewEvent(carried, root).ChangedLines(file); !lines.Covers(2) || lines.Covers(5) {
		t.Error("the moment's own lines were thrown away for a file nobody wrote, because its clock runs ahead of ours")
	}

	if err := os.WriteFile(file, []byte("x = 1\ny = 2\nz = 3\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if lines := NewEvent(carried, root).ChangedLines(file); !lines.Covers(5) {
		t.Error("a moment whose file moved again is still marked with the lines of the version that is gone")
	}
}
