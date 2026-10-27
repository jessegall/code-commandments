package health

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"
)

// The journal allows 200ms for a health answer and restarts a service that is late; five late answers in a row mark
// the plugin broken and alert the user. These hold the answer to being free: said from what the process knows by
// running, on a listener of its own, as many times as the journal asks.

// window is the journal's own: a service that has not answered within it counts as not responding.
const window = 200 * time.Millisecond

// shortFolder is a folder to name a socket in. A socket's name has a length limit of about a hundred characters,
// which the folder a test is handed runs past on its own, and which is why the journal makes the path rather than
// the plugin naming one inside the project.
func shortFolder(t *testing.T) string {
	t.Helper()
	folder, err := os.MkdirTemp("", "s")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.RemoveAll(folder) })

	return folder
}

// asked is the journal's question put to a service listening at the path, and what it answered.
func asked(t *testing.T, path string) up {
	t.Helper()

	connection, err := net.DialTimeout("unix", path, window)
	if err != nil {
		t.Fatalf("the service does not answer at %s: %v", path, err)
	}
	defer connection.Close()

	connection.SetDeadline(time.Now().Add(window))
	if _, err := connection.Write([]byte(Request + "\n")); err != nil {
		t.Fatalf("the question could not be written: %v", err)
	}

	line, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil {
		t.Fatalf("the service wrote no answer inside %v: %v", window, err)
	}

	var said up
	if err := json.Unmarshal([]byte(line), &said); err != nil {
		t.Fatalf("the journal cannot read %q: %v", line, err)
	}

	return said
}

// TestAServiceSaysItIsUpAsOftenAsItIsAsked holds the endpoint to what the journal does with it: it is called on a
// schedule, so one answer is no proof. The service names itself in every answer, which is how the journal knows
// which service to restart when one of them goes quiet.
func TestAServiceSaysItIsUpAsOftenAsItIsAsked(t *testing.T) {
	path := filepath.Join(shortFolder(t), "roslyn.sock")
	stop := serveOn(path, "roslyn")
	defer stop()

	for at := 0; at < 3; at++ {
		said := asked(t, path)
		if said.Health != "up" || said.Service != "roslyn" {
			t.Fatalf("answer %d reads %+v", at+1, said)
		}
	}
}

// TestEveryAnswerIsWellInsideTheWindowTheJournalAllows measures what the schedule depends on. The answer is a
// constant string written on accept, so the whole cost is the dial and the write; anything the service would have
// to work out to say it would be measured here and fail on the machine that made this necessary.
func TestEveryAnswerIsWellInsideTheWindowTheJournalAllows(t *testing.T) {
	path := filepath.Join(shortFolder(t), "hooks.sock")
	stop := serveOn(path, "hooks")
	defer stop()

	// A tenth of the window, for ten answers, leaves room for a machine under load and still proves the answer
	// costs nothing: a judge of one file took twenty seconds of wall time where this is asked to take a tenth of a
	// second.
	started := time.Now()
	for at := 0; at < 10; at++ {
		asked(t, path)
	}

	if took := time.Since(started); took > window/2 {
		t.Errorf("ten answers took %v, where the journal allows %v for one", took, window)
	}
}

// TestTheQuestionIsAnsweredWhileTheServiceIsBusy holds the whole reason the health socket is its own listener. The
// bridge services answer one request at a time, and a parse of a project takes seconds; a health question queued
// behind that work would be late, and the journal would restart a service that is doing exactly what it is for.
func TestTheQuestionIsAnsweredWhileTheServiceIsBusy(t *testing.T) {
	path := filepath.Join(shortFolder(t), "mypy.sock")
	stop := serveOn(path, "mypy")
	defer stop()

	// The service's own work: a goroutine that will not come back inside the window, as a bridge halfway through a
	// project does not.
	working := make(chan struct{})
	defer close(working)
	go func() { <-working }()

	if said := asked(t, path); said.Health != "up" {
		t.Errorf("a busy service answered %+v", said)
	}
}

// TestTheSocketIsTheOneTheJournalNamed holds the wiring the manifest declares: the service listens where
// $COMMANDMENTS_HEALTH_SOCKET says. The journal makes that path because a socket's name has a length limit that a
// path inside the project would run past, which is why the plugin's own folder is not where this lives.
func TestTheSocketIsTheOneTheJournalNamed(t *testing.T) {
	path := filepath.Join(shortFolder(t), "hooks.sock")
	t.Setenv(Socket, path)

	stop := Serve("hooks")
	defer stop()

	if said := asked(t, path); said.Service != "hooks" {
		t.Errorf("the service at the named socket answered %+v", said)
	}
}

// TestWithNoSocketNamedNothingIsServed holds the plugin outside the journal: run by hand, or started by a journal
// that asks no service how it is, a service serves no health and still stops cleanly.
func TestWithNoSocketNamedNothingIsServed(t *testing.T) {
	t.Setenv(Socket, "")

	Serve("hooks")()
}

// TestTheStoppedServiceAnswersNoMore holds the other end: the journal removes a service and starts it again on an
// upgrade, and a listener left behind would answer for a process that is gone.
func TestTheStoppedServiceAnswersNoMore(t *testing.T) {
	path := filepath.Join(shortFolder(t), "php.sock")
	stop := serveOn(path, "php")
	asked(t, path)
	stop()

	if connection, err := net.DialTimeout("unix", path, window); err == nil {
		connection.SetDeadline(time.Now().Add(window))
		connection.Write([]byte(Request + "\n"))
		if line, err := bufio.NewReader(connection).ReadString('\n'); err == nil {
			t.Errorf("the stopped service still answers %q", line)
		}
		connection.Close()
	}
}

// TestTheHealthQuestionIsToldApartFromAnEventOfTheAgentsWork holds the one place the two meet: the hooks service
// answers the journal's events on the socket the journal named for them, and the health question arriving there is
// answered as health, never read as a moment to judge.
func TestTheHealthQuestionIsToldApartFromAnEventOfTheAgentsWork(t *testing.T) {
	for name, line := range map[string]map[string]any{
		"the question by its own key": {"health": 1},
		"the question as an event":    {"v": 1, "event": "health"},
	} {
		if !Asked(line) {
			t.Errorf("%s is not read as the health question: %v", name, line)
		}
	}

	for name, line := range map[string]map[string]any{
		"a file the agent edited": {"v": 1, "event": "file_edited", "data": map[string]any{"path": "src/refund.py"}},
		"a command about to run":  {"v": 1, "event": "command_starting"},
		"nothing at all":          {},
	} {
		if Asked(line) {
			t.Errorf("%s is read as the health question: %v", name, line)
		}
	}
}
