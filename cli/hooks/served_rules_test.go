package hooks

import (
	"net"
	"os"
	"path/filepath"
	"testing"
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
