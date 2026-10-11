package agents

import (
	"go/parser"
	"go/token"
	"strings"
	"testing"
)

// TestAnAgentKnowsNothingOfHooks holds the direction between the two: an agent says what it is — the folder it
// discovers skills in, the file it reads instructions from, whether it enforces — and sync acts on that, wiring the
// hooks for one that enforces. The import the other way made hooks sit below agents below sync, so nothing could
// both stand above hooks and reach agents, which is why the plugin's own command could not write the briefing
// block. A hook is a harness's protocol, not an agent's knowledge, so this package never names that one.
func TestAnAgentKnowsNothingOfHooks(t *testing.T) {
	files, err := parser.ParseDir(token.NewFileSet(), ".", nil, parser.ImportsOnly)
	if err != nil {
		t.Fatal(err)
	}

	for _, pkg := range files {
		for name, file := range pkg.Files {
			for _, imported := range file.Imports {
				if strings.HasSuffix(strings.Trim(imported.Path.Value, `"`), "/cli/hooks") {
					t.Errorf("%s imports cli/hooks; an agent declares that it Enforces and sync wires the hooks", name)
				}
			}
		}
	}
}
