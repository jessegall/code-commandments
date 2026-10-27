package task

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// What the task verbs answer was proved only by the recorded parity cases, which run the built binary. The command
// reads the session's own task list and writes to a console, so its answers are held here, and the ones chosen are
// the refusals: a verb that quietly does nothing reads exactly like a verb that worked.

// told runs the command inside the project and answers its exit code and what it said.
func told(t *testing.T, project string, args ...string) (code int, said string, warned string) {
	t.Helper()
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(project); err != nil {
		t.Fatal(err)
	}
	defer os.Chdir(was)

	var out, errs bytes.Buffer
	code, err = Command{}.Run(cli.InputOf("task", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String(), errs.String()
}

// aProjectWithASession is a project a session can keep its tasks in.
func aProjectWithASession(t *testing.T) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, ".git"), 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// TestAVerbTheToolDoesNotHaveSaysSo holds the answer to a mistyped verb, which otherwise prints the board and reads
// as if the verb had run.
func TestAVerbTheToolDoesNotHaveSaysSo(t *testing.T) {
	code, _, warned := told(t, aProjectWithASession(t), "juggle")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "juggle") {
		t.Errorf("it says %q, which does not name the verb that was typed", warned)
	}
}

// TestAddingWithoutATitleSaysWhatToType holds the one argument the verb cannot do without.
func TestAddingWithoutATitleSaysWhatToType(t *testing.T) {
	code, _, warned := told(t, aProjectWithASession(t), "add")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "task add") {
		t.Errorf("it says %q, which does not say what to type", warned)
	}
}

// TestATaskUnderOneThatIsNotThereIsRefused holds --under: a subtask of a task nobody has would be written where
// nothing points at it, so the verb refuses rather than queueing it loose.
func TestATaskUnderOneThatIsNotThereIsRefused(t *testing.T) {
	project := aProjectWithASession(t)

	for name, under := range map[string]string{
		"a number nobody has":           "--under=9",
		"an address that is no address": "--under=1.x",
	} {
		t.Run(name, func(t *testing.T) {
			code, said, warned := told(t, project, "add", under, "Sub")

			if code == 0 {
				t.Errorf("it queued the task anyway:\n%s", said)
			}
			if strings.TrimSpace(said+warned) == "" {
				t.Error("it refused without saying why")
			}
		})
	}
}

// TestStartingATaskNobodyHasSaysSo holds the refusal a reader needs most: starting a number that is not there must
// not read as having started something.
func TestStartingATaskNobodyHasSaysSo(t *testing.T) {
	project := aProjectWithASession(t)

	for name, id := range map[string]string{
		"a number nobody has": "7",
		"no number at all":    "",
		"zero":                "0",
	} {
		t.Run(name, func(t *testing.T) {
			args := []string{"start"}
			if id != "" {
				args = append(args, id)
			}

			code, said, warned := told(t, project, args...)

			if code == 0 {
				t.Errorf("it answered as if a task had started:\n%s", said)
			}
			if strings.TrimSpace(said+warned) == "" {
				t.Error("it refused without saying why")
			}
		})
	}
}

// TestATaskIsQueuedStartedAndClosed holds the whole life a reader puts a task through, and that each step says
// which task it was about.
func TestATaskIsQueuedStartedAndClosed(t *testing.T) {
	project := aProjectWithASession(t)

	code, queued, warned := told(t, project, "add", "Port the CLI", "because Go is the whole tool")
	if code != 0 {
		t.Fatalf("adding exits %d, saying %q", code, warned)
	}
	if !strings.Contains(queued, "Port the CLI") {
		t.Errorf("adding says %q, which does not name the task", queued)
	}

	if code, said, warned := told(t, project, "start", "1"); code != 0 {
		t.Fatalf("starting exits %d, saying %q and %q", code, said, warned)
	}

	code, closed, warned := told(t, project, "done", "1", "it landed")
	if code != 0 {
		t.Fatalf("closing exits %d, saying %q", code, warned)
	}
	if !strings.Contains(closed, "Port the CLI") {
		t.Errorf("closing says %q, which does not name the task", closed)
	}
}

// TestClosingWithoutSayingWhatCameOfItIsRefused holds what history keeps: a closed task with no outcome is a row
// that says something happened and not what.
func TestClosingWithoutSayingWhatCameOfItIsRefused(t *testing.T) {
	project := aProjectWithASession(t)

	if code, _, warned := told(t, project, "add", "Port the CLI"); code != 0 {
		t.Fatalf("adding exits %d, saying %q", code, warned)
	}

	code, _, warned := told(t, project, "done", "1")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "what came of it") {
		t.Errorf("it says %q, which does not say what is missing", warned)
	}
}

// TestClosingATaskTwiceSaysItIsAlreadyClosed holds the second attempt, and points the reader at its log rather than
// closing it again.
func TestClosingATaskTwiceSaysItIsAlreadyClosed(t *testing.T) {
	project := aProjectWithASession(t)

	if code, _, warned := told(t, project, "add", "Port the CLI"); code != 0 {
		t.Fatalf("adding exits %d, saying %q", code, warned)
	}
	if code, _, warned := told(t, project, "done", "1", "it landed"); code != 0 {
		t.Fatalf("closing exits %d, saying %q", code, warned)
	}

	code, said, _ := told(t, project, "done", "1", "again")

	if code == 0 {
		t.Error("a closed task was closed again")
	}
	if !strings.Contains(said, "already closed") {
		t.Errorf("it says %q, which does not say the task is already closed", said)
	}
}

// TestAnEmptyBoardSaysThereIsNothingRatherThanNothingAtAll holds the first thing a reader sees in a fresh session.
func TestAnEmptyBoardSaysThereIsNothingRatherThanNothingAtAll(t *testing.T) {
	code, said, warned := told(t, aProjectWithASession(t))

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if strings.TrimSpace(said) == "" {
		t.Error("an empty board printed nothing at all")
	}
}
