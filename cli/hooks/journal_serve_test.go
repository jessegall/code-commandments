package hooks

import (
	"bufio"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"
)

// TestAGateIsAnsweredWhileAnEarlierMomentsAdviceStillRuns holds the service to answering the next write's gate
// while the sin check of the one before is still working, and to checking one moment at a time.
func TestAGateIsAnsweredWhileAnEarlierMomentsAdviceStillRuns(t *testing.T) {
	folder := t.TempDir()
	t.Setenv("JOURNAL_QUEUE", filepath.Join(folder, "queue"))
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

	release, started := make(chan struct{}), make(chan struct{}, 2)
	running, most := 0, 0
	advice := startAdviser(func(map[string]any) {
		running++
		most = max(most, running)
		started <- struct{}{}
		<-release
		running--
	})
	go serve(listener, func() bool { return true }, advice)

	ask(t, socket)
	<-started

	answered := make(chan struct{})
	go func() {
		ask(t, socket)
		close(answered)
	}()

	select {
	case <-answered:
	case <-time.After(5 * time.Second):
		t.Fatal("the second gate waited on the first moment's advice")
	}

	release <- struct{}{}
	<-started
	release <- struct{}{}

	if most != 1 {
		t.Errorf("%d advice ran at once", most)
	}
}

// ask sends one moment to the service and reads its answer.
func ask(t *testing.T, socket string) {
	t.Helper()
	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Error(err)

		return
	}
	defer connection.Close()
	connection.Write([]byte(`{"event":"hook.PreToolUse","tool":{"name":"Write","file":"src/A.php"}}` + "\n"))
	if _, err := bufio.NewReader(connection).ReadString('\n'); err != nil {
		t.Error(err)
	}
}
