package service

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"path/filepath"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/scan"
)

func TestABridgeServiceSaysItIsUpWithNothingToDo(t *testing.T) {
	project := t.TempDir()
	os.WriteFile(project+"/cart.py", []byte("x = 1\n"), 0o644)
	was, _ := os.Getwd()
	os.Chdir(project)
	t.Cleanup(func() { os.Chdir(was) })
	t.Setenv("CLAUDE_PROJECT_DIR", project)

	short, err := os.MkdirTemp("/tmp", "s")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(short)
	socket := filepath.Join(short, "roslyn.sock")
	t.Setenv(health.Socket, socket)

	out := &lockedBuffer{}
	ended := make(chan int, 1)
	go func() {
		code, _ := Serve{Bridge: scan.CSharp}.Run(&cli.Input{}, cli.Console{Out: out, Err: out})
		ended <- code
	}()

	var said map[string]any
	for deadline := time.Now().Add(5 * time.Second); said == nil; time.Sleep(10 * time.Millisecond) {
		if time.Now().After(deadline) {
			t.Fatalf("the service never said how it is: %q", out.String())
		}
		said = howItIs(t, socket)
	}

	if said["health"] != "up" || said["service"] != "roslyn" {
		t.Errorf("the service answered %v", said)
	}

	syscall.Kill(os.Getpid(), syscall.SIGTERM)
	select {
	case <-ended:
	case <-time.After(5 * time.Second):
		t.Fatal("the service did not stop")
	}

	if answered := howItIs(t, socket); answered != nil {
		t.Errorf("the stopped service still answers %v", answered)
	}
}

func howItIs(t *testing.T, socket string) map[string]any {
	t.Helper()

	connection, err := net.DialTimeout("unix", socket, 200*time.Millisecond)
	if err != nil {
		return nil
	}
	defer connection.Close()

	connection.SetDeadline(time.Now().Add(200 * time.Millisecond))
	if _, err := connection.Write([]byte(health.Request + "\n")); err != nil {
		return nil
	}

	line, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil {
		return nil
	}

	var said map[string]any
	if err := json.Unmarshal([]byte(line), &said); err != nil {
		t.Fatalf("the journal cannot read %q: %v", line, err)
	}

	return said
}
