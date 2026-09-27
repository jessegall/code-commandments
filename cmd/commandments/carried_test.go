package main

import (
	"go/ast"
	"go/parser"
	"go/token"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// TestTheBinaryFindsNothingThroughItsSourcePath holds every package the binary is built from to carrying what it
// reads: a file found through runtime.Caller names a path on the machine that built the binary, not the one it
// runs on.
func TestTheBinaryFindsNothingThroughItsSourcePath(t *testing.T) {
	listed, err := exec.Command("go", "list", "-deps", "-f", "{{if not .Standard}}{{.Dir}}|{{join .GoFiles \"|\"}}{{end}}", ".").Output()
	if err != nil {
		t.Fatal(err)
	}
	for _, line := range strings.Split(strings.TrimSpace(string(listed)), "\n") {
		files := strings.Split(line, "|")
		for _, file := range files[1:] {
			path := filepath.Join(files[0], file)
			parsed, err := parser.ParseFile(token.NewFileSet(), path, nil, 0)
			if err != nil {
				t.Fatal(err)
			}
			ast.Inspect(parsed, func(node ast.Node) bool {
				if called, ok := node.(*ast.SelectorExpr); ok && called.Sel.Name == "Caller" {
					if receiver, ok := called.X.(*ast.Ident); ok && receiver.Name == "runtime" {
						t.Errorf("%s finds a file through runtime.Caller", path)
					}
				}

				return true
			})
		}
	}
}
