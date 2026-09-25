package task

import (
	"bytes"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

var noon = time.Date(2026, 1, 1, 12, 0, 0, 0, time.UTC)

func TestATaskIsWrittenAndLoggedInThePhpFormat(t *testing.T) {
	tasks := Tasks{t.TempDir()}
	added, written := tasks.Add(ID{}, "Port the CLI — all of it!", "because", noon)

	if !written || added.Path != tasks.Root+"/queue/001-port-the-cli-all-of-it.md" {
		t.Fatalf("added %+v", added)
	}

	moved, _ := tasks.Move(added, Done, "it landed", noon.Add(time.Hour))
	raw, _ := os.ReadFile(moved.Path)
	want := "# Port the CLI — all of it!\n\nbecause\n\n- queued 2026-01-01 12:00\n- done 2026-01-01 13:00 — it landed\n"

	if string(raw) != want {
		t.Errorf("file\n%q\nwant\n%q", raw, want)
	}
}

func TestAnAddressIsReadAndRenderedAsPhpDoes(t *testing.T) {
	for text, want := range map[string]string{"1": "001", " 002.10 ": "002.10", "1234.1": "1234.1"} {
		if id, parsed := ParseID(text); !parsed || id.Render() != want {
			t.Errorf("%q: %v %v", text, id, parsed)
		}
	}

	for _, bad := range []string{"", "0", "1.", "a", "-1", "1.0", "+1"} {
		if _, parsed := ParseID(bad); parsed {
			t.Errorf("%q parsed", bad)
		}
	}
}

func TestStaleListsActiveTasksUntouchedPastTheCutoff(t *testing.T) {
	root := t.TempDir()
	tasks := Tasks{root}
	os.MkdirAll(root+"/active", 0o755)
	os.WriteFile(root+"/active/001-old.md", []byte("# Old\n"), 0o644)
	os.WriteFile(root+"/active/002-fresh.md", []byte("# Fresh\n"), 0o644)
	os.Chtimes(root+"/active/001-old.md", noon.Add(-90*time.Minute), noon.Add(-90*time.Minute))
	os.Chtimes(root+"/active/002-fresh.md", noon.Add(-10*time.Minute), noon.Add(-10*time.Minute))

	var out bytes.Buffer
	command := Command{Now: func() time.Time { return noon }}
	command.stale(tasks, cli.InputOf("task", "stale"), cli.Console{Out: &out, Err: &out})

	if want := "Untouched for 60m or more:\n  001  Old — 90m\n"; out.String() != want {
		t.Errorf("stale %q", out.String())
	}

	out.Reset()
	command.stale(tasks, cli.InputOf("task", "stale", "--for=5"), cli.Console{Out: &out, Err: &out})

	if !strings.Contains(out.String(), "002  Fresh — 10m") {
		t.Errorf("--for=5 %q", out.String())
	}
}
