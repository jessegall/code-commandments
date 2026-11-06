package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"path/filepath"
	"strings"
	"sync/atomic"
	"testing"
	"time"
)

func TestAnAnswerNeverWaitsOnAJudging(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	held := make(chan struct{})
	var judgings atomic.Int64

	socket := filepath.Join(t.TempDir(), "hooks.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	advice := startAdvisers(func(given asked) {
		judgings.Add(1)
		<-held
	})

	go serve(listener, func() bool { return true }, advice)

	for move := 0; move < 3; move++ {
		said := answerOn(t, socket, map[string]any{
			"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
			"cwd":  root,
			"data": map[string]any{"cwd": root, "kind": "create", "path": under},
		})

		if said == "" {
			t.Fatalf("move %d was answered with nothing at all while a judging was held", move)
		}

		if strings.Contains(said, "deep-python-nesting") {
			t.Fatalf("move %d came back with what a judging found, so the answer waited on it:\n%s", move, said)
		}
	}

	if judgings.Load() == 0 {
		t.Fatal("no judging was ever started, so nothing was being waited on and this test proves nothing")
	}

	close(held)
}

func TestTheCommandGateAnswersWhileAJudgingIsHeld(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	socket := filepath.Join(t.TempDir(), "hooks.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	held := make(chan struct{})
	var judgings atomic.Int64

	advice := startAdvisers(func(given asked) {
		judgings.Add(1)
		<-held
	})

	go serve(listener, func() bool { return true }, advice)

	answerOn(t, socket, map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd":  root,
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if judgings.Load() == 0 {
		t.Fatal("no judging was started, so the gate was not asked while anything was in flight")
	}

	for _, command := range []string{"git push", "rm -rf src", "git commit -m the work"} {
		said := answerOn(t, socket, map[string]any{
			"v": 1, "event": "command_starting", "session": "s1", "environment": "main", "agent": 1,
			"cwd":  root,
			"data": map[string]any{"cwd": root, "tool": "Bash", "command": command},
		})

		if said == "" {
			t.Fatalf("the gate on %q was answered with nothing while a judging was held", command)
		}

		if strings.Contains(said, "deep-python-nesting") {
			t.Fatalf("the gate on %q came back with what a judging found, so it waited on one:\n%s", command, said)
		}
	}

	close(held)
}

func answerOn(t *testing.T, socket string, event map[string]any) string {
	t.Helper()

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	line, err := json.Marshal(event)
	if err != nil {
		t.Fatal(err)
	}

	if _, err := connection.Write(append(line, '\n')); err != nil {
		t.Fatal(err)
	}

	connection.SetDeadline(time.Now().Add(20 * time.Second))

	said, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil && said == "" {
		t.Fatalf("the service answered nothing: %v", err)
	}

	return strings.TrimSpace(said)
}
