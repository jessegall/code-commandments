package bridge

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

// withMypy points the bridge at an interpreter that already has mypy: $COMMANDMENTS_MYPY_PYTHON, else the one
// the PHP tool built, so a test never installs anything.
func withMypy(t *testing.T) []string {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		home, _ := os.UserHomeDir()
		built, _ := filepath.Glob(filepath.Join(home, ".cache/code-commandments/mypy-bridge/*/venv/bin/python"))
		for _, candidate := range built {
			if exec.Command(candidate, "-c", "import mypy").Run() == nil {
				python = candidate
				break
			}
		}
	}
	if python == "" {
		t.Skip("no Python with mypy: set COMMANDMENTS_MYPY_PYTHON")
	}
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", python)
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	command, err := Mypy()
	if err != nil {
		t.Fatal(err)
	}

	return command
}

func fixture(t *testing.T, path string) string {
	absolute, err := filepath.Abs(filepath.Join("../tests/Fixtures/python", path))
	if err != nil {
		t.Fatal(err)
	}

	return absolute
}

func TestTheMypyBridgeWritesAValidTreeOfThePythonFixture(t *testing.T) {
	stream, err := Once(withMypy(t), fixture(t, ""))
	if err != nil {
		t.Fatal(err)
	}
	if stream.Header.Language != contract.Python {
		t.Errorf("the stream is %s, not python", stream.Header.Language)
	}
	if len(stream.Files) == 0 {
		t.Error("the stream holds no file")
	}
	if stream.Program == nil || len(stream.Program.Packages) == 0 {
		t.Error("the program line names no package")
	}
}

func TestAServedBridgeAnswersEachRequestWithAWholeStream(t *testing.T) {
	server, err := Serve(withMypy(t))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	cli := fixture(t, "shop/cli.py")
	first, err := server.Ask(Request{Paths: []string{fixture(t, "")}, Write: []string{cli}})
	if err != nil {
		t.Fatal(err)
	}
	for _, file := range first.Files {
		if file.Context == (file.Path == cli) {
			t.Errorf("%s: context is %v", file.Path, file.Context)
		}
	}
	second, err := server.Ask(Request{Paths: []string{cli}})
	if err != nil {
		t.Fatal(err)
	}
	if len(second.Files) != 1 || second.Files[0].Context {
		t.Errorf("the second answer holds %d files", len(second.Files))
	}
}
