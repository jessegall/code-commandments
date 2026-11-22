package engine

import (
	"slices"
	"strings"
	"testing"
)

func TestAnUnsetCallSpeaksOfRemovingWhereAFunctionOfTheSameNameDoesNot(t *testing.T) {
	header := strings.Replace(versionFiveHeader, `"language": "python"`, `"language": "php"`, 1)
	file := `{"file": {"path": "/src/tests/fixtures/reader.py", "language": "php", "errors": 0, "module": "App",
"root": {"id": 0, "kind": "file", "span": [0, 90, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 90, 1], "name": "App", "parts": {"statements": [
  {"id": 2, "kind": "expression-statement", "span": [0, 20, 1], "parts": {"expression":
    {"id": 3, "kind": "call", "span": [0, 19, 1], "parts": {"callee": {"id": 4, "kind": "reference", "span": [0, 5, 1], "name": "unset", "form": "name"},
      "arguments": [{"id": 5, "kind": "argument", "span": [6, 18, 1], "parts": {"value": {"id": 6, "kind": "reference", "span": [6, 18, 1], "name": "cache", "form": "name"}}}]}}}},
  {"id": 7, "kind": "expression-statement", "span": [21, 40, 2], "parts": {"expression":
    {"id": 8, "kind": "call", "span": [21, 39, 2], "parts": {"callee": {"id": 9, "kind": "reference", "span": [21, 26, 2], "name": "unset", "form": "name", "refers": "App\\unset"},
      "arguments": []}}}}]}}]}},
"comments": []}}`
	var lines []string
	for _, line := range []string{header, file, versionFiveTrailer} {
		lines = append(lines, strings.Join(strings.Fields(line), " "))
	}
	codebase, err := FromString(strings.Join(lines, "\n")+"\n", map[string]string{"/src/tests/fixtures/reader.py": strings.Repeat(" ", 90)})
	if err != nil {
		t.Fatal(err)
	}
	calls := codebase.WhereKind("call").Get()

	if words := calls[0].CodeWords(); !slices.Contains(words, "remov") {
		t.Errorf("unset(...) speaks of %v, without removing", words)
	}
	if words := calls[1].CodeWords(); slices.Contains(words, "remov") {
		t.Errorf("a project function named unset speaks of %v, as if it were the language's removal", words)
	}
}
