package agents

import (
	"go/ast"
	"go/parser"
	"go/token"
	"strings"
	"testing"
)

func TestAnAgentKnowsNothingOfHooks(t *testing.T) {
	files, err := parser.ParseDir(token.NewFileSet(), ".", nil, parser.ImportsOnly)
	if err != nil {
		t.Fatal(err)
	}

	for _, pkg := range files {
		for name, file := range pkg.Files {
			if imports(file, "/cli/hooks") {
				t.Errorf("%s imports cli/hooks; an agent declares that it Enforces and sync wires the hooks", name)
			}
		}
	}
}

func imports(file *ast.File, path string) bool {
	for _, imported := range file.Imports {
		if strings.HasSuffix(strings.Trim(imported.Path.Value, `"`), path) {
			return true
		}
	}

	return false
}
