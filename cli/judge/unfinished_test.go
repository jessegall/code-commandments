package judge

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// TestARunThatCouldNotFinishGivesNoTick holds what a run says when it finds nothing while a part of it never ran:
// no green tick, and the failure as the last thing printed. An agent's worker read "No sins found in 4 changed
// files" as a pass although its Vue bridge had ended with 661 files unread, and read the red line below it as
// noise after the answer.
//
// The bridge here ends with no trailer, as that one did, which is a bridge that broke off and not one that was
// never there: what it was asked to read goes unread and every rule over it is a part of the run that could not
// finish.
func TestARunThatCouldNotFinishGivesNoTick(t *testing.T) {
	root := t.TempDir()
	for path, contents := range map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/Label.vue":             "<template>\n    <span>a label</span>\n</template>\n",
	} {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	broken := t.TempDir()
	if err := os.WriteFile(filepath.Join(broken, "node"), []byte("#!/bin/sh\nexit 0\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("PATH", broken+string(os.PathListSeparator)+os.Getenv("PATH"))
	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	var out, errs bytes.Buffer
	var code int
	stderrOf(t, func() {
		answered, err := Command{}.Run(cli.InputOf("judge", "src", "--no-checklist", "--parallel=1"), cli.Console{Out: &out, Err: &errs})
		if err != nil {
			t.Fatal(err)
		}
		code = answered
	})

	said := out.String()
	if code != aRuleCouldNotRun {
		t.Fatalf("a run whose bridge broke off answered %d:\n%s\n%s", code, said, errs.String())
	}
	if strings.Contains(said, "✓") {
		t.Errorf("a run that could not finish still ticked:\n%s", said)
	}
	at := strings.Index(said, "could not finish")
	if at < 0 {
		t.Fatalf("the run never said a part of it could not finish:\n%s", said)
	}
	if strings.Contains(said[at:], "No sins found") {
		t.Errorf("what the run did judge is printed after the failure, so the failure is not the last word:\n%s", said)
	}
}

// TestAWholeRunThatFindsNothingTicks holds the other half at the one place the line is written: a run every part of
// which finished says so with its tick, so a tick keeps meaning a verdict.
func TestAWholeRunThatFindsNothingTicks(t *testing.T) {
	whole := Skipped{}.Verdict("No sins found in 4 files, the lines changed in the working tree, of 860 read")
	if !strings.Contains(whole, "✓") || !strings.HasSuffix(strings.TrimSuffix(whole, "\033[0m"), "read.") {
		t.Errorf("a whole run says %q", whole)
	}

	short := Skipped{"the Vue and TypeScript bridge"}.Verdict("No sins found in 4 files, of 860 read")
	if strings.Contains(short, "✓") || strings.Contains(short, "\033[32m") {
		t.Errorf("a run that could not finish says %q", short)
	}
	if !strings.HasSuffix(short, ":") {
		t.Errorf("what a run that could not finish judged does not read into the failure below it: %q", short)
	}
}

// TestABridgeThatIsNotThereIsAPartThatCouldNotFinish holds the quieter half of the same fault. A bridge whose
// toolchain is not installed leaves its files unread, says so on STDERR and answers no error, because every other
// language is still judged. So a run whose every Python file went unread printed its tick and answered 0, and
// nothing a reader or a pipeline sees tells that from a clean run.
func TestABridgeThatIsNotThereIsAPartThatCouldNotFinish(t *testing.T) {
	root := t.TempDir()
	for path, contents := range map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/Label.vue":             "<template>\n    <span>a label</span>\n</template>\n",
	} {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// No node anywhere on the PATH, which is a bridge that was never there rather than one that broke off.
	t.Setenv("PATH", t.TempDir())
	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	var out, errs bytes.Buffer
	var code int
	stderrOf(t, func() {
		answered, err := Command{}.Run(cli.InputOf("judge", "src", "--no-checklist", "--parallel=1"), cli.Console{Out: &out, Err: &errs})
		if err != nil {
			t.Fatal(err)
		}
		code = answered
	})

	said := out.String()
	if code != aRuleCouldNotRun {
		t.Fatalf("a run whose bridge is not installed answered %d:\n%s\n%s", code, said, errs.String())
	}
	if strings.Contains(said, "\u2713") {
		t.Errorf("a run that read none of its files still ticked:\n%s", said)
	}
	if !strings.Contains(said, "the Vue and TypeScript bridge") {
		t.Errorf("the run does not name the bridge that read nothing:\n%s", said)
	}
}
