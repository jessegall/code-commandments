package frontend

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestAFilesTreeDoesNotAnswerForTheWholeProgram holds a file's tree to its own code: a file reaching a global is
// written the same whether it is read alone or beside the rest of its folder. `typeof globalThis` is the type that
// broke this — described, it holds every ambient name there is, each printed as however many declaration files have
// merged into it, so a file's tree changed with how many files the program was built over, which is what makes a
// tree cache answer for the whole scan rather than for the file.
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

// recordOf is the stream's line for the file whose path ends in name.
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
