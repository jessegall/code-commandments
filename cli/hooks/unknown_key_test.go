package hooks

import (
	"encoding/json"
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

func TestAKeyWeHaveNoHandlerForIsAnsweredCleanlyAndNeverAsAFault(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	for _, line := range []map[string]any{
		{"v": 1, "event": "suites.coding.workers.worker_worktree.cut", "agent": 1, "cwd": root},
		{"v": 1, "event": "something.nobody.wrote.yet", "agent": 1, "cwd": root},
		{"v": 1, "event": "worker_clocking_out", "agent": 1, "cwd": root, "data": map[string]any{}},
		{"v": 1, "agent": 1, "cwd": root},
		{"v": 1, "event": "", "agent": 1},
	} {
		said, code := answeredBy(t, line)

		if code != 0 {
			t.Errorf("the key %q was answered with code %d, and five faults switch the plugin off", line["event"], code)
		}

		if said == "" {
			continue
		}

		var answer map[string]any
		if err := json.Unmarshal([]byte(said), &answer); err != nil {
			t.Errorf("the key %q was answered with something that is not a line of JSON: %q (%v)", line["event"], said, err)

			continue
		}
		answer = asTheJournalSees(answer)

		if asText(answer["answer"]) == "refuse" {
			t.Errorf("the key %q was refused, and a key we do not know must never hold anything up: %s", line["event"], said)
		}
	}
}

func answeredBy(t *testing.T, line map[string]any) (string, int) {
	t.Helper()

	given, err := json.Marshal(line)
	if err != nil {
		t.Fatal(err)
	}

	fed, err := os.CreateTemp(t.TempDir(), "line-*.json")
	if err != nil {
		t.Fatal(err)
	}

	if _, err := fed.Write(given); err != nil {
		t.Fatal(err)
	}

	if _, err := fed.Seek(0, 0); err != nil {
		t.Fatal(err)
	}

	was := os.Stdin
	os.Stdin = fed
	defer func() {
		os.Stdin = was
		fed.Close()
	}()

	in := cli.InputOf("plugin-hook")

	out := &strings.Builder{}
	errs := &strings.Builder{}

	code, err := (PluginHook{}).Run(in, cli.Console{Out: out, Err: errs})
	if err != nil {
		t.Errorf("the key %q answered with an error: %v", line["event"], err)
	}

	return strings.TrimSpace(out.String()), code
}
