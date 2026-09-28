package hooks

import (
	"bufio"
	"io"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

// TestTheServiceWaitsForItsStopWhenItCannotListen holds journal-serve to staying up on a socket it cannot listen on,
// as a Windows binary cannot on MSYS2's, so the journal does not start it again, and to ending cleanly on its stop.
func TestTheServiceWaitsForItsStopWhenItCannotListen(t *testing.T) {
	t.Setenv(pluginSocket, filepath.Join(t.TempDir(), "missing", "hooks.sock"))
	said, err := io.Pipe()
	defer said.Close()
	defer err.Close()

	ended := make(chan int, 1)
	go func() {
		code, _ := JournalServe{}.Run(&cli.Input{}, cli.Console{Out: io.Discard, Err: err})
		ended <- code
	}()

	warned := make(chan string, 1)
	go func() {
		line, _ := bufio.NewReader(said).ReadString('\n')
		warned <- line
		io.Copy(io.Discard, said)
	}()
	select {
	case line := <-warned:
		if !strings.Contains(line, "Cannot listen on") {
			t.Fatalf("the service said %q", line)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("the service said nothing of the socket it cannot listen on")
	}
	select {
	case code := <-ended:
		t.Fatalf("the service ended (%d) before it was stopped", code)
	case <-time.After(200 * time.Millisecond):
	}

	syscall.Kill(os.Getpid(), syscall.SIGTERM)
	select {
	case code := <-ended:
		if code != 0 {
			t.Errorf("the stopped service ended %d", code)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("the service did not stop")
	}
}
