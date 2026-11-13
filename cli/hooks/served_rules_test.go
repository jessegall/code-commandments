package hooks

import (
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"

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
