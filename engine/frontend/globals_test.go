package frontend

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

// written is the stream the bridge writes for the arguments, as text.
func written(t *testing.T, held Bridge, arguments ...string) string {
	t.Helper()
	command, err := held.Command()
	if err != nil {
		t.Skip(err)
	}
	out, err := exec.Command(command[0], append(command[1:], bridge.ListedArguments(arguments...)...)...).Output()
	if err != nil {
		t.Fatalf("%v: %v", command, err)
	}

	return string(out)
}

func TestAFilesTreeDoesNotAnswerForTheWholeProgram(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	files := map[string]string{
		"network.ts": "export const online = globalThis.navigator.onLine\nexport const shape = { online }\n",
		"other.ts":   "export const url = new URL('https://example.test')\nexport const held = new Map<string, number>()\n",
	}
	for path, source := range files {
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	alone := recordOf(t, written(t, Here(), filepath.Join(root, "network.ts")), "network.ts")
	beside := recordOf(t, written(t, Here(), root), "network.ts")
	if alone != beside {
		t.Errorf("network.ts is written as %d bytes alone and %d beside its folder", len(alone), len(beside))
	}
	if at := strings.Index(alone, "\"name\":\"atob\""); at >= 0 {
		t.Errorf("the tree describes an ambient shape's fields, which belong to no code the file wrote: %s", alone[max(0, at-700):at+60])
	}
}

func recordOf(t *testing.T, stream string, name string) string {
	t.Helper()
	for _, line := range strings.Split(stream, "\n") {
		if strings.Contains(line, "\"path\":\"") && strings.Contains(line, name+"\"") {
			return line
		}
	}
	t.Fatalf("the stream holds no line for %s", name)

	return ""
}
