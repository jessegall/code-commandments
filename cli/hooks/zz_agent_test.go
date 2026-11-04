package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
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

// TestTheMomentCarriesTheAgentTheEventNamed holds the fact where the service keeps it. The service answers the
// journal inside a window and works the rest out afterwards, and the only thing it keeps of the event is the
// moment it read from it: a moment that drops the agent's number leaves the answer naming nobody, however right
// the event was.
func TestTheMomentCarriesTheAgentTheEventNamed(t *testing.T) {
	for _, each := range []struct {
		why   string
		named any
		want  int
	}{
		{"an event of agent 78 is a moment of agent 78", float64(78), 78},
		{"an event that names no agent is a moment of nobody", nil, 0},
	} {
		moment, known := Moment4Of(map[string]any{
			"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": each.named,
			"data": map[string]any{"path": "src/refund.py", "kind": "edit"},
		})
		if !known {
			t.Fatalf("%s: the event stands for no moment", each.why)
		}

		if got := MomentOf(Stamped(moment)).Agent; got != each.want {
			t.Errorf("%s: the moment reads as agent %d, want %d", each.why, got, each.want)
		}
	}
}

// TestAnEditJudgedOnTheServicesPathIsQueuedForTheAgentThatMadeIt is the proof on the path that answers here: the
// service, over its socket, judging after the answer went back. The program's own path read the agent off the
// event and was right; the service kept only the moment, which carried no number, so every mark it worked out
// named nobody and the journal gave it to the first agent that drained the queue — a worker was told about the
// main agent's edit twice over.
func TestAnEditJudgedOnTheServicesPathIsQueuedForTheAgentThatMadeIt(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	// A socket's path is short by the operating system's rule, which a test's own folder is not: bound under one,
	// listening answers "invalid argument" and the proof would skip itself.
	short, err := os.MkdirTemp("", "s")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(short)

	socket := filepath.Join(short, "serve.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatalf("no socket to listen on: %v", err)
	}
	defer listener.Close()

	go serve(listener, func() bool { return true }, startAdviser(adviseAsked))

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	said, err := json.Marshal(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41},
	})
	if err != nil {
		t.Fatal(err)
	}
	if _, err := connection.Write(append(said, '\n')); err != nil {
		t.Fatal(err)
	}
	if _, err := bufio.NewReader(connection).ReadString('\n'); err != nil {
		t.Fatal(err)
	}

	queued := queuedWithin(t, data, time.Minute)
	if len(queued) != 1 {
		t.Fatalf("the service left %d answer(s) in the queue", len(queued))
	}

	if asAgent(queued[0]["agent"]) != 78 {
		t.Errorf("the answer is for agent %v, so the journal hands it to whoever drains the queue first: %v", queued[0]["agent"], queued[0])
	}

	if !strings.Contains(asText(queued[0]["tell"]), "redundant-python-else") {
		t.Errorf("the answer names no sin, so it proves nothing about whose it is: %v", queued[0])
	}
}

// queuedWithin is the queue once the judge has written to it, waited for: the service answers at once and works
// the moment out afterwards, so there is nothing to hold on to but the file appearing.
func queuedWithin(t *testing.T, data string, within time.Duration) []map[string]any {
	t.Helper()
	queue, _ := Queue4In(data)
	until := time.Now().Add(within)

	for time.Now().Before(until) {
		if files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json")); len(files) > 0 {
			return drained(t, data)
		}

		time.Sleep(50 * time.Millisecond)
	}

	t.Fatalf("nothing reached the queue in %s", within)

	return nil
}
