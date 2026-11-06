package task

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

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

func TestAVerbTheToolDoesNotHaveSaysSo(t *testing.T) {
	code, _, warned := told(t, aProjectWithASession(t), "juggle")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "juggle") {
		t.Errorf("it says %q, which does not name the verb that was typed", warned)
	}
}

func TestAddingWithoutATitleSaysWhatToType(t *testing.T) {
	code, _, warned := told(t, aProjectWithASession(t), "add")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "task add") {
		t.Errorf("it says %q, which does not say what to type", warned)
	}
}

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

func TestAnEmptyBoardSaysThereIsNothingRatherThanNothingAtAll(t *testing.T) {
	code, said, warned := told(t, aProjectWithASession(t))

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, warned)
	}
	if strings.TrimSpace(said) == "" {
		t.Error("an empty board printed nothing at all")
	}
}
