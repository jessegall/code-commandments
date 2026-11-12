package scan

import (
	"bytes"
	"errors"
	"net"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

func TestARunSaysWhenTheWarmBridgeCouldNotAnswer(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, project)

	listener, err := net.Listen("unix", bridge.ServiceSocket("php", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go func() {
		for {
			connection, err := listener.Accept()
			if err != nil {
				return
			}
			connection.Close()
		}
	}()

	said := swapStderr(t)
	held := &process{
		held:  Bridge{Kind: "php", Label: "PHP"},
		reach: Process{Command: func([]string) ([]string, error) { return nil, errors.New("no bridge of its own in this test") }},
		roots: []string{project},
	}

	for range 2 {
		held.AskTallied(bridge.Request{Paths: bridge.Listed(project)}, func() {})
	}

	words := said()
	if count := bytes.Count([]byte(words), []byte("could not answer")); count != 1 {
		t.Errorf("the missed warm bridge is said %d times, not once: %q", count, words)
	}
	if !bytes.Contains([]byte(words), []byte("php")) {
		t.Errorf("what is said names no bridge: %q", words)
	}
}

func TestARunSaysNothingWhenNoServiceIsKeptUp(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, project)

	said := swapStderr(t)
	held := &process{
		held:  Bridge{Kind: "php", Label: "PHP"},
		reach: Process{Command: func([]string) ([]string, error) { return nil, errors.New("no bridge of its own in this test") }},
		roots: []string{project},
	}
	held.AskTallied(bridge.Request{Paths: bridge.Listed(project)}, func() {})

	if words := said(); bytes.Contains([]byte(words), []byte("could not answer")) {
		t.Errorf("a run with no service to miss says one was missed: %q", words)
	}
}

func swapStderr(t *testing.T) func() string {
	t.Helper()
	read, write, err := os.Pipe()
	if err != nil {
		t.Fatal(err)
	}
	was := os.Stderr
	os.Stderr = write
	t.Cleanup(func() { os.Stderr = was })

	return func() string {
		write.Close()
		var taken bytes.Buffer
		taken.ReadFrom(read)

		return taken.String()
	}
}

func chdir(t *testing.T, folder string) {
	t.Helper()

	previous, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Chdir(folder); err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { os.Chdir(previous) })
}
