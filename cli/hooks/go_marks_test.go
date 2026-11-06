package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

func goProject(t *testing.T) (root, edited, sibling string) {
	t.Helper()
	root = t.TempDir()
	write := func(name, text string) string {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}

		return path
	}

	write(".commandments/config.json", `{"paths": ["shop"]}`)
	write("go.mod", "module probe.example/shop\n")
	edited = write("shop/ledger.go", "package shop\n\nfunc Post(entries []Entry) int {\n\tposted := 0\n\tfor _, entry := range entries {\n\t\tif !entry.Void && entry.Cents != 0 {\n\t\t\tposted += entry.Cents\n\t\t\tposted++\n\t\t}\n\t}\n\n\treturn posted\n}\n")
	sibling = write("shop/entry.go", "package shop\n\ntype Entry struct {\n\tCents int\n\tVoid  bool\n}\n\nfunc Counted(entries []Entry) int {\n\tcounted := 0\n\tfor _, entry := range entries {\n\t\tif entry.Cents > 0 {\n\t\t\tcounted++\n\t\t\tcounted += entry.Cents\n\t\t}\n\t}\n\n\treturn counted\n}\n")

	return root, edited, sibling
}

func TestAGoFileIsJudgedAsItIsEdited(t *testing.T) {
	root, edited, _ := goProject(t)

	marks := goMarks(t, root, edited)
	if len(marks) == 0 {
		t.Fatal("a Go file a session edited raises no sin")
	}
	for _, mark := range marks {
		if sin := mark.Rule.Sin().Definition().Name; sin != "go-loop-wrapped-in-if" {
			t.Errorf("the Go file raises %s, which is another language's rule", sin)
		}
	}
}

func TestAGosSiblingsSinIsNotTheEditedFilesMark(t *testing.T) {
	root, edited, sibling := goProject(t)

	for _, mark := range goMarks(t, root, edited) {
		if strings.HasSuffix(mark.Found(), filepath.Base(sibling)+":11") || strings.Contains(mark.Found(), filepath.Base(sibling)) {
			t.Errorf("editing one Go file marks its sibling's sin: %s", mark.Found())
		}
	}
}

func TestAGoPackageIsReadWholeToJudgeOneOfItsFiles(t *testing.T) {
	_, edited, _ := goProject(t)

	codebase, err := scan.OneFile(edited).Load()
	if err != nil {
		t.Fatalf("one Go file does not load: %v", err)
	}
	if len(codebase.Files()) < 2 {
		t.Errorf("a Go file is read as %d file(s), so a sibling's types are unknown to it", len(codebase.Files()))
	}
}

func TestAWorkerCarryingAGoSinIsHeld(t *testing.T) {
	root, _, _ := goProject(t)
	repository(t, root)

	held := DeliveryHeld(deliveringOf(root, generousWindow, "shop/ledger.go"))
	if held == "" {
		t.Fatal("a worker carrying a Go sin delivered its work unheld")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hold answered %q: %v", held, err)
	}
	if answer["answer"] != "refuse" {
		t.Errorf("the hold answered %v, want a refusal", answer["answer"])
	}
	if reason := asText(answer["reason"]); !strings.Contains(reason, "go-loop-wrapped-in-if") {
		t.Errorf("the reason reads %q, naming no Go sin", reason)
	}
}

func TestAChangedGoFileIsCountedInTheSessionsWorklist(t *testing.T) {
	root, edited, _ := goProject(t)
	repository(t, root, "-c", "user.email=a@b", "-c", "user.name=a", "commit", "-q", "--allow-empty", "-m", "a checkout")

	written := map[string]any{"session_id": "writer", "tool_name": "Write",
		"tool_input": map[string]any{"file_path": "shop/ledger.go"}}
	JudgeReminder{}.Handle(NewEvent(with(written, "PreToolUse"), root))
	if err := os.WriteFile(edited, []byte("package shop\n\nfunc Post() int { return 0 }\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	JudgeReminder{}.Handle(NewEvent(with(written, "PostToolUse"), root))

	reminded := JudgeReminder{}.Handle(NewEvent(map[string]any{"hook_event_name": "Stop", "session_id": "writer"}, root))
	if reminded.IsSilent() || !strings.Contains(reminded.JSON("Stop"), "changed 1 judged file") {
		t.Fatalf("a session that rewrote %s is not reminded of it: %s", filepath.Base(edited), reminded.JSON("Stop"))
	}
}

func repository(t *testing.T, root string, then ...string) {
	t.Helper()
	runs := [][]string{{"init", "-q"}}
	if len(then) > 0 {
		runs = append(runs, then)
	}
	for _, args := range runs {
		if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", args, out)
		}
	}
}

func goMarks(t *testing.T, root, file string) []SinMark {
	t.Helper()
	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}
	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}

	found, _, _ := marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), git.ChangedLines{})

	return found
}
