// Package bridges proves every bridge writes the expected trees of contract/samples/testdata/expected.
package bridges

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/samples"
)

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

