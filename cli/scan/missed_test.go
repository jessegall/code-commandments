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

// TestARunSaysWhenTheWarmBridgeCouldNotAnswer holds the one thing that tells two slow runs apart. A run that finds
// no service and a run whose service is up and cannot answer both just take the long way round, and the second is a
// fault: every run pays a start-up it should not have paid, with a warm bridge sitting unreached beside it. It is
// said once, because a service that went away mid-session is ordinary and needs no second word.
func TestARunSaysWhenTheWarmBridgeCouldNotAnswer(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, project)

	// A listener that accepts and says nothing is a service that is up and cannot answer, which is the state this
	// is about: dialling it succeeds, and the ask does not.
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
		held: Bridge{Kind: "php", Label: "PHP"},
		// No bridge of its own either: this is about what is SAID when the warm one was reached and could not
		// answer, and a run that then cannot start one of its own says that separately.
		reach: Process{Command: func([]string) ([]string, error) { return nil, errors.New("no bridge of its own in this test") }},
		roots: []string{project},
	}

	for range 2 {
		held.AskTallied(bridge.Request{Paths: []string{project}}, func() {})
	}

	words := said()
	if count := bytes.Count([]byte(words), []byte("could not answer")); count != 1 {
		t.Errorf("the missed warm bridge is said %d times, not once: %q", count, words)
	}
	if !bytes.Contains([]byte(words), []byte("php")) {
		t.Errorf("what is said names no bridge: %q", words)
	}
}

// TestARunSaysNothingWhenNoServiceIsKeptUp holds the quiet case: a project whose session keeps no bridge up is the
// ordinary one, and a run of the tool says nothing about it.
func TestARunSaysNothingWhenNoServiceIsKeptUp(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, project)

	said := swapStderr(t)
	held := &process{
		held: Bridge{Kind: "php", Label: "PHP"},
		// No bridge of its own either: this is about what is SAID when the warm one was reached and could not
		// answer, and a run that then cannot start one of its own says that separately.
		reach: Process{Command: func([]string) ([]string, error) { return nil, errors.New("no bridge of its own in this test") }},
		roots: []string{project},
	}
	held.AskTallied(bridge.Request{Paths: []string{project}}, func() {})

	if words := said(); bytes.Contains([]byte(words), []byte("could not answer")) {
		t.Errorf("a run with no service to miss says one was missed: %q", words)
	}
}

// swapStderr takes what the run writes to STDERR, and answers it as text once the test asks.
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

// chdir puts the test in the folder and puts the working folder back when it ends. It is what t.Chdir does, which
// the go this module declares has not got: os.Chdir alone leaves every later test in the package standing
// somewhere else, and the failures then read as regressions in tests that never moved.
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
