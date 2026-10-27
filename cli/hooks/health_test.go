package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/health"
)

// The journal asks each service of a plugin how it is, on a schedule, and restarts one that has not answered inside
// 200ms; five silences in a row mark the plugin broken. The hooks service answers on the socket it already answers
// the journal's events on, so there is nothing for the journal to find out to ask it.

// TestTheServiceSaysHowItIsOnTheSocketItAlreadyAnswersOn holds that answer to costing nothing: it is given from what
// the process knows by running, with no moment read and no judge started, and it is never handed to the adviser as
// a moment of the agent's work.
func TestTheServiceSaysHowItIsOnTheSocketItAlreadyAnswersOn(t *testing.T) {
	short, err := os.MkdirTemp("", "s")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(short)

	socket := filepath.Join(short, "serve.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()

	advised := make(chan asked, 1)
	go serve(listener, func() bool { return true }, startAdviser(func(moment asked) { advised <- moment }))

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	connection.SetDeadline(time.Now().Add(200 * time.Millisecond))
	if _, err := connection.Write([]byte(health.Request + "\n")); err != nil {
		t.Fatal(err)
	}

	line, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil {
		t.Fatalf("the service said nothing of how it is inside the window the journal allows: %v", err)
	}

	var said map[string]any
	if err := json.Unmarshal([]byte(line), &said); err != nil {
		t.Fatalf("the journal cannot read %q: %v", line, err)
	}
	if said["health"] != "up" || said["service"] != "hooks" {
		t.Errorf("the service answered %v", said)
	}

	select {
	case moment := <-advised:
		t.Errorf("the question of how the service is was taken for a moment to judge: %v", moment.given)
	case <-time.After(200 * time.Millisecond):
	}
}
