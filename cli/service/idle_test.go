package service

import (
	"os"
	"strings"
	"sync"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

// lockedBuffer is the service's output, read while it runs.
type lockedBuffer struct {
	mu   sync.Mutex
	text strings.Builder
}

func (b *lockedBuffer) Write(p []byte) (int, error) {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.text.Write(p)
}

func (b *lockedBuffer) String() string {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.text.String()
}

// TestAProjectWithNoCSharpKeepsNoBridge holds the service to a project with no C#: it fetches and starts nothing,
// says why, and stays up until the session stops it, so the journal never starts it again in a loop.
func TestAProjectWithNoCSharpKeepsNoBridge(t *testing.T) {
	project := t.TempDir()
	os.WriteFile(project+"/cart.py", []byte("x = 1\n"), 0o644)
	was, _ := os.Getwd()
	os.Chdir(project)
	t.Cleanup(func() { os.Chdir(was) })
	t.Setenv("CLAUDE_PROJECT_DIR", project)

	out := &lockedBuffer{}
	ended := make(chan int, 1)
	go func() {
		code, _ := Serve{Bridge: CSharp}.Run(&cli.Input{}, cli.Console{Out: out, Err: out})
		ended <- code
	}()

	for deadline := time.Now().Add(5 * time.Second); !strings.Contains(out.String(), "no C# to judge"); time.Sleep(10 * time.Millisecond) {
		if time.Now().After(deadline) {
			t.Fatalf("the service said %q", out.String())
		}
	}
	select {
	case code := <-ended:
		t.Fatalf("the service ended (%d) before it was stopped: %q", code, out.String())
	case <-time.After(200 * time.Millisecond):
	}

	syscall.Kill(os.Getpid(), syscall.SIGTERM)
	select {
	case code := <-ended:
		if code != 0 {
			t.Errorf("the stopped service ended %d: %q", code, out.String())
		}
	case <-time.After(5 * time.Second):
		t.Fatal("the service did not stop")
	}
}
