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

const window = 200 * time.Millisecond

func shortFolder(t *testing.T) string {
	t.Helper()
	folder, err := os.MkdirTemp("", "s")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.RemoveAll(folder) })

	return folder
}

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

func TestEveryAnswerIsWellInsideTheWindowTheJournalAllows(t *testing.T) {
	path := filepath.Join(shortFolder(t), "hooks.sock")
	stop := serveOn(path, "hooks")
	defer stop()

	started := time.Now()
	for at := 0; at < 10; at++ {
		asked(t, path)
	}

	if took := time.Since(started); took > window/2 {
		t.Errorf("ten answers took %v, where the journal allows %v for one", took, window)
	}
}

func TestTheQuestionIsAnsweredWhileTheServiceIsBusy(t *testing.T) {
	path := filepath.Join(shortFolder(t), "mypy.sock")
	stop := serveOn(path, "mypy")
	defer stop()

	working := make(chan struct{})
	defer close(working)
	go func() { <-working }()

	if said := asked(t, path); said.Health != "up" {
		t.Errorf("a busy service answered %+v", said)
	}
}

func TestTheSocketIsTheOneTheJournalNamed(t *testing.T) {
	path := filepath.Join(shortFolder(t), "hooks.sock")
	t.Setenv(Socket, path)

	stop := Serve("hooks")
	defer stop()

	if said := asked(t, path); said.Service != "hooks" {
		t.Errorf("the service at the named socket answered %+v", said)
	}
}

func TestWithNoSocketNamedNothingIsServed(t *testing.T) {
	t.Setenv(Socket, "")

	Serve("hooks")()
}

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
