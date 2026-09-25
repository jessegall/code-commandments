package contract

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

// mypyPython is an interpreter with mypy installed: $COMMANDMENTS_MYPY_PYTHON, else the one the PHP tool built.
func mypyPython(t *testing.T) string {
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return python
	}
	home, _ := os.UserHomeDir()
	built, _ := filepath.Glob(filepath.Join(home, ".cache/code-commandments/mypy-bridge/*/venv/bin/python"))
	for _, python := range built {
		if exec.Command(python, "-c", "import mypy").Run() == nil {
			return python
		}
	}
	t.Skip("no Python with mypy: set COMMANDMENTS_MYPY_PYTHON")

	return ""
}

func TestTheMypyBridgeWritesAValidTreeOfThePythonFixture(t *testing.T) {
	fixture, err := filepath.Abs(fixtures + "python")
	if err != nil {
		t.Fatal(err)
	}
	var out, errs bytes.Buffer
	bridge := exec.Command(mypyPython(t), "../bridge/mypy/tree.py", fixture)
	bridge.Stdout, bridge.Stderr = &out, &errs
	if err := bridge.Run(); err != nil {
		t.Fatalf("the bridge failed: %v\n%s", err, errs.String())
	}
	stream, err := ReadAll(&out)
	if err != nil {
		t.Fatal(err)
	}
	if stream.Header.Language != Python {
		t.Errorf("the stream is %s, not python", stream.Header.Language)
	}
	if len(stream.Files) == 0 || stream.Trailer.Files != len(stream.Files) {
		t.Errorf("the trailer counts %d files; the stream holds %d", stream.Trailer.Files, len(stream.Files))
	}
	if stream.Program == nil || len(stream.Program.Packages) == 0 {
		t.Error("the program line names no package")
	}
}
