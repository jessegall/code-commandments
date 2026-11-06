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

	"github.com/jessegall/code-commandments/cli/health"
)

func TestTheHealthAnswerDoesNotQueueBehindTheWork(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	socket := shortSocket(t)
	t.Setenv(health.Socket, socket)

	stop := health.Serve(hooksService)
	t.Cleanup(stop)

	hooks := shortSocket(t)
	listener, err := net.Listen("unix", hooks)
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

	for move := 0; move < 3; move++ {
		answerOn(t, hooks, map[string]any{
			"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
			"cwd":  root,
			"data": map[string]any{"cwd": root, "kind": "create", "path": under},
		})
	}

	if judgings.Load() == 0 {
		t.Fatal("no judging was started, so nothing was holding the service and this test proves nothing")
	}

	said := healthAnsweredOn(t, socket)

	if !strings.Contains(said, `"health":"up"`) {
		t.Fatalf("the health socket answered %q while the service was busy, and their supervisor restarts the whole group for that, which costs the tree and every overlay", said)
	}

	onTheHooks := answerOn(t, hooks, map[string]any{"health": 1})
	if !strings.Contains(onTheHooks, `"health":"up"`) {
		t.Errorf("the answering socket does not answer a health question before the work: %q", onTheHooks)
	}

	close(held)
}

func healthAnsweredOn(t *testing.T, socket string) string {
	t.Helper()

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatalf("the health socket could not be reached at all: %v", err)
	}
	defer connection.Close()

	connection.SetDeadline(time.Now().Add(5 * time.Second))

	said, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil && said == "" {
		t.Fatalf("the health socket answered nothing: %v", err)
	}

	var answer map[string]any
	if json.Unmarshal([]byte(strings.TrimSpace(said)), &answer) != nil {
		return strings.TrimSpace(said)
	}

	return strings.TrimSpace(said)
}
