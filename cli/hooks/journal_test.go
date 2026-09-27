package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

// TestAdviceIsQueuedAsThePHPToolQueuesIt holds the queue to what the PHP tool wrote for the same advice,
// recorded in testdata/queued.
func TestAdviceIsQueuedAsThePHPToolQueuesIt(t *testing.T) {
	whisper := "Code Commandments — the edit: it breaks a rule that is described at such length that its first line runs well past eighty characters.\n  • it's here\n\n    and here"
	golang := filepath.Join(t.TempDir(), "queue")

	answer := JournalAnswer{Whisper: &whisper, Raises: []Raise{{"sin-found", "array-bag at src/A.php:3", "sins/x"}, {Event: "sin-resolved", Brief: "a\nb"}}}

	if err := (Queue{golang}).Tell(answer, MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "o'brien"})); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile("testdata/queued")
	got, _ := os.ReadFile(golang)

	if string(got) != string(want) {
		t.Errorf("queued\n%s\nthe PHP tool queued\n%s", got, want)
	}
}
