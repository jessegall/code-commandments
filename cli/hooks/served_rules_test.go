package hooks

import (
	"bufio"
	"net"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/engine"
)

const ownRule = `{"engine": "backend", "sin": {"name": "own-rule", "description": "written while the service ran", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"name": "select"}]}}`

func writeProjectFile(t *testing.T, path, contents string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}
}

func listeningOnAShortSocket(t *testing.T) (net.Listener, string) {
	t.Helper()

	short, err := os.MkdirTemp("", "serve")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.RemoveAll(short) })

	socket := filepath.Join(short, "serve.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	return listener, socket
}

func TestARuleFileChangeLeavesTheServiceRunningAndTheNextJudgingReadsTheRule(t *testing.T) {
	root := t.TempDir()
	listener, socket := listeningOnAShortSocket(t)

	started := stamp()
	returned := make(chan struct{})
	go func() {
		serve(listener, func() bool { return stamp() == started }, startAdvisers(func(asked) {}))
		close(returned)
	}()

	if rules := len(custom.InForce(root).Rules); rules != 0 {
		t.Fatalf("%d rules before the rule is written", rules)
	}

	writeProjectFile(t, filepath.Join(root, ".commandments", "custom", "OwnRule.json"), ownRule)

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	connection.SetDeadline(time.Now().Add(5 * time.Second))
	if _, err := connection.Write([]byte(health.Request + "\n")); err != nil {
		t.Fatal(err)
	}

	if _, err := bufio.NewReader(connection).ReadString('\n'); err != nil {
		t.Fatalf("a rule was written and the service stopped answering: %v", err)
	}

	select {
	case <-returned:
		t.Error("a rule was written and the service stopped serving")
	default:
	}

	if rules := len(custom.InForce(root).Rules); rules != 1 {
		t.Errorf("%d rules read after the rule was written, so the next judging cannot use it", rules)
	}
}

func TestAChangedBinaryEndsTheServiceAndTheServiceLogSaysSo(t *testing.T) {
	captured := capturedServiceLog(t, true)
	listener, socket := listeningOnAShortSocket(t)

	returned := make(chan struct{})
	go func() {
		serve(listener, func() bool { return false }, startAdvisers(func(asked) {}))
		close(returned)
	}()

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	select {
	case <-returned:
	case <-time.After(5 * time.Second):
		t.Fatal("the service did not stop for the changed binary")
	}

	said := captured.String()
	for _, named := range []string{"stopped", "hooks service", "binary"} {
		if !strings.Contains(said, named) {
			t.Errorf("the service log does not say %q: %s", named, said)
		}
	}
}

func TestAConfigFileChangeOfThePathsReadLetsGoOfTheHeldTree(t *testing.T) {
	root := t.TempDir()
	settings := filepath.Join(root, ".commandments", "config.json")

	writeProjectFile(t, settings, `{"paths": ["src"]}`)
	walkedTheTreeUnder(root)

	kept.Hold(root, "main", []string{root}, engine.Load(), nil)
	t.Cleanup(func() { kept.Drop(root, "main") })

	heldTheConfigFileOf(root)
	if _, held := kept.Holds(root, "main"); !held {
		t.Fatal("the config file was not written and the tree was let go")
	}

	time.Sleep(10 * time.Millisecond)
	writeProjectFile(t, settings, `{"paths": ["src"], "exclude": ["src/generated"]}`)

	heldTheConfigFileOf(root)
	if _, held := kept.Holds(root, "main"); held {
		t.Error("the paths the tree is read under changed and the service holds the tree it read before")
	}
}
