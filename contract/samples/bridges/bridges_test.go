// Package bridges proves every bridge writes the expected trees of contract/samples/testdata/expected.
package bridges

import (
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/samples"
)

// update rewrites each expected tree from what its bridge writes now, keeping the tree's own path and module: run it
// once after a bridge deliberately writes more, then read the diff of the trees before committing them.
var update = flag.Bool("update", false, "rewrite the expected trees from what the bridges write")

var bridgeOf = map[contract.Language]scan.Bridge{
	contract.PHP:        scan.PHPBridge,
	contract.Python:     scan.Python,
	contract.CSharp:     scan.CSharp,
	contract.TypeScript: scan.Frontend,
	contract.Vue:        scan.Frontend,
	contract.Go:         scan.GoBridge,
}

// TestEveryBridgeWritesTheExpectedTree runs each language's bridge on every expected tree's source and compares what
// it writes with the tree. A bridge not yet writing version 5 fails its subtests by name.
func TestEveryBridgeWritesTheExpectedTree(t *testing.T) {
	all, err := samples.AllExpected("../testdata/expected")
	if err != nil {
		t.Fatal(err)
	}
	for _, expected := range all {
		t.Run(string(expected.Language)+"/"+expected.Name(), func(t *testing.T) {
			root := t.TempDir()
			placed, err := expected.Placed(root)
			if err != nil {
				t.Fatal(err)
			}
			stream, err := bridgeOf[expected.Language].Read([]string{root}, []string{placed}, func() {})
			if err != nil {
				t.Fatalf("the %s bridge could not read %s: %v", expected.Language, expected.Name(), err)
			}
			written := writtenFile(stream, placed)
			if written == nil {
				t.Fatalf("the %s bridge wrote no file for %s", expected.Language, placed)
			}
			if *update {
				rewrite(t, expected, written, root)

				return
			}
			for _, difference := range samples.Differences(expected.File, written) {
				t.Error(difference)
			}
		})
	}
}

func writtenFile(stream *contract.Stream, placed string) *contract.File {
	for _, file := range stream.Files {
		if file.Path == placed || strings.HasSuffix(file.Path, "/"+filepath.Base(placed)) {
			return file
		}
	}

	return nil
}

// rewrite writes the expected tree anew: its header as it was, and the file the bridge wrote under the tree's own path
// and module, every fact that spells the run's temporary folder left out, since it differs each run.
func rewrite(t *testing.T, expected samples.Expected, written *contract.File, root string) {
	t.Helper()
	written.Path, written.Module = expected.File.Path, expected.File.Module
	file, err := json.Marshal(map[string]any{"file": written})
	if err != nil {
		t.Fatal(err)
	}
	var held any
	if err := json.Unmarshal(file, &held); err != nil {
		t.Fatal(err)
	}
	resolved, _ := filepath.EvalSymlinks(root)
	file, err = json.Marshal(withoutFolder(withoutFolder(held, root), resolved))
	if err != nil {
		t.Fatal(err)
	}
	header, err := json.Marshal(map[string]any{"header": expected.Header})
	if err != nil {
		t.Fatal(err)
	}
	trailer := []byte(`{"trailer": {"files": 1}}`)
	if err := os.WriteFile(expected.Tree, append(append(append(append(header, '\n'), file...), '\n'), append(trailer, '\n')...), 0o644); err != nil {
		t.Fatal(err)
	}
}

// withoutFolder is the decoded JSON with every object field whose text spells the folder left out.
func withoutFolder(held any, folder string) any {
	switch value := held.(type) {
	case map[string]any:
		for key, field := range value {
			if spellsFolder(field, folder) || factSpellsFolder(field, folder) {
				delete(value, key)
			} else {
				value[key] = withoutFolder(field, folder)
			}
		}
	case []any:
		for at, item := range value {
			value[at] = withoutFolder(item, folder)
		}
	}

	return held
}

// spellsFolder says whether a decoded JSON value is text, or a list holding text, that spells the folder.
func spellsFolder(held any, folder string) bool {
	if folder == "" {
		return false
	}
	switch value := held.(type) {
	case string:
		return strings.Contains(value, folder)
	case []any:
		for _, item := range value {
			if text, isText := item.(string); isText && strings.Contains(text, folder) {
				return true
			}
		}
	}

	return false
}

// factSpellsFolder says whether a decoded JSON object is a fact, not a node (it has no kind) nor the file (no root), one of whose own fields
// spells the folder: a call's target is compared whole, so it goes whole.
func factSpellsFolder(held any, folder string) bool {
	fact, isObject := held.(map[string]any)
	if !isObject || fact["kind"] != nil || fact["root"] != nil {
		return false
	}
	for _, field := range fact {
		if spellsFolder(field, folder) {
			return true
		}
	}

	return false
}
