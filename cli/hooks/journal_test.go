package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAdviceIsQueuedInTheMomentsEnvironment(t *testing.T) {
	whisper := "Code Commandments — the edit: it breaks a rule that is described at such length that its first line runs well past eighty characters.\n  • it's here\n\n    and here"
	folder := t.TempDir()
	named := filepath.Join(folder, "code-commandments.queue")

	answer := JournalAnswer{
		Whisper: &whisper,
		Raises:  []Raise{{Event: "sin-found", Brief: "array-bag at src/A.php:3", Open: "sins/x", Key: "sin:a1"}, {Event: "sin-resolved", Brief: "a\nb"}},
		Settles: []string{"sin:b2"},
	}

	if err := (Queue{named}).Tell(answer, MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "o'brien"})); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile("testdata/queued")
	got, _ := os.ReadFile(filepath.Join(folder, "code-commandments.o'brien.queue"))

	if string(got) != string(want) || strings.Contains(string(got), "--env") {
		t.Errorf("queued\n%s\nwant\n%s", got, want)
	}

	if _, err := os.Stat(named); !os.IsNotExist(err) {
		t.Errorf("the default environment's queue was written: %v", err)
	}

	if (Queue{named}).For(MomentOf(map[string]any{"env": "../main"})).path != named {
		t.Error("an environment no file name can carry left the queue as named")
	}
}

func TestAdviceOfNoEnvironmentKeepsTheQueueTheJournalNamed(t *testing.T) {
	named := filepath.Join(t.TempDir(), "code-commandments.queue")
	whisper := "Code Commandments — a sin"

	if err := (Queue{named}).Tell(JournalAnswer{Whisper: &whisper}, MomentOf(map[string]any{"event": "hook.PostToolUse"})); err != nil {
		t.Fatal(err)
	}

	if got, _ := os.ReadFile(named); !strings.HasPrefix(string(got), "nudge create ") {
		t.Errorf("queued %q", got)
	}
}

func TestAdviceIsQueuedWhereThePayloadSays(t *testing.T) {
	whisper := "Code Commandments — the edit breaks a rule."
	folder := t.TempDir()
	named, helpers := filepath.Join(folder, "code-commandments.queue"), filepath.Join(folder, "helper", "code-commandments.queue")
	if err := os.MkdirAll(filepath.Dir(helpers), 0o755); err != nil {
		t.Fatal(err)
	}
	answer := JournalAnswer{Whisper: &whisper, Raises: []Raise{{Event: "sin-resolved", Brief: "array-bag at src/A.vue:3"}}}
	moment := MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "main-mies-buildwright", "plugin": map[string]any{"queue": helpers}})

	if err := (Queue{named}).Tell(answer, moment); err != nil {
		t.Fatal(err)
	}

	if written, _ := os.ReadFile(helpers); !strings.Contains(string(written), "sin-resolved") {
		t.Errorf("the helper's queue holds %q", written)
	}
	for _, other := range []string{named, filepath.Join(folder, "code-commandments.main-mies-buildwright.queue")} {
		if _, err := os.Stat(other); !os.IsNotExist(err) {
			t.Errorf("%s was written: %v", filepath.Base(other), err)
		}
	}
}
