package hooks

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

// TestAQueuedAnswerNamesTheAgentItIsFor holds the isolation a shared environment needs. The journal hands a
// queued answer to the first agent whose hook drains the queue UNLESS the file names one, and a worker and the
// main agent share an environment: while this wrote only the environment, a worker was told about the main
// agent's edit — a file it had never seen, in a tree it does not have. That really happened.
func TestAQueuedAnswerNamesTheAgentItIsFor(t *testing.T) {
	words := "two sins"

	for _, each := range []struct {
		why   string
		agent int
		named bool
	}{
		{"an answer for agent 7 says so, and the journal hands it to nobody else", 7, true},
		{"an event that named no agent names none, so the journal hands it on as it always did", 0, false},
	} {
		folder := t.TempDir()
		queue, queued := Queue4In(folder)
		if !queued {
			t.Fatal("no queue in a folder that is named")
		}

		if err := queue.Tell(JournalAnswer{Whisper: &words}, "main", each.agent); err != nil {
			t.Fatal(err)
		}

		files, _ := filepath.Glob(filepath.Join(folder, "queue", "*.json"))
		if len(files) != 1 {
			t.Fatalf("%s: %d files queued", each.why, len(files))
		}

		text, err := os.ReadFile(files[0])
		if err != nil {
			t.Fatal(err)
		}

		var read map[string]any
		if err := json.Unmarshal(text, &read); err != nil {
			t.Fatal(err)
		}

		held, names := read["agent"]
		switch {
		case each.named && !names:
			t.Errorf("%s: the file names no agent: %s", each.why, text)
		case each.named && int(held.(float64)) != each.agent:
			t.Errorf("%s: the file names agent %v", each.why, held)
		case !each.named && names:
			t.Errorf("%s: the file names agent %v", each.why, held)
		}
	}
}

// TestAnEventsAgentIsReadHoweverItsNumberArrives holds the reading to the one thing it must not do: answer the
// first agent for an event that named none. The journal numbers its agents from 1, so 0 is nobody.
func TestAnEventsAgentIsReadHoweverItsNumberArrives(t *testing.T) {
	for _, each := range []struct {
		given any
		want  int
	}{
		{float64(7), 7},
		{7, 7},
		{json.Number("7"), 7},
		{nil, 0},
		{"7", 0},
		{json.Number("not a number"), 0},
	} {
		if got := asAgent(each.given); got != each.want {
			t.Errorf("an agent given as %#v reads as %d, want %d", each.given, got, each.want)
		}
	}
}
