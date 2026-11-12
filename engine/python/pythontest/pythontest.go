// Package pythontest builds a codebase from Python sources for a test, through the real bridge.
package pythontest

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/python"
)

func FromSource(t testing.TB, sources map[string]string) *engine.Codebase {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for path, source := range sources {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	stream, err := bridge.Once(bridgetest.Mypy(t), bridge.Listed(root)...)
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}

func File(t testing.TB, codebase *engine.Codebase, path string) *engine.File {
	t.Helper()
	for _, file := range codebase.Files() {
		if strings.HasSuffix(file.Path, "/"+path) {
			return file
		}
	}
	t.Fatalf("no file %s", path)

	return nil
}
