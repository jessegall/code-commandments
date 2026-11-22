// Package bridges proves every bridge writes the expected trees of contract/samples/testdata/expected.
package bridges

import (
	"bytes"
	"flag"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
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
	byLanguage := map[contract.Language][]samples.Expected{}
	for _, expected := range all {
		byLanguage[expected.Language] = append(byLanguage[expected.Language], expected)
	}
	for language, held := range byLanguage {
		t.Run(string(language), func(t *testing.T) {
			t.Parallel()
			served := servedBridges{t: t, roots: map[contract.Language]string{}, askers: map[contract.Language]bridge.Asker{}}
			defer served.close()
			for _, expected := range held {
				t.Run(expected.Name(), func(t *testing.T) {
					sampleWritten(t, &served, expected)
				})
			}
		})
	}
}

func sampleWritten(t *testing.T, served *servedBridges, expected samples.Expected) {
	root, asker, err := served.of(expected.Language)
	if err != nil {
		t.Fatalf("the %s bridge could not start: %v", expected.Language, err)
	}
	placed, err := expected.Placed(root)
	if err != nil {
		t.Fatal(err)
	}
	stream, err := asker.AskTallied(bridge.Request{Paths: []string{placed}, Write: []string{placed}}, func() {})
	if err != nil {
		t.Fatalf("the %s bridge could not read %s: %v", expected.Language, expected.Name(), err)
	}
	written := writtenFile(stream, placed)
	if written == nil {
		t.Fatalf("the %s bridge wrote no file for %s", expected.Language, placed)
	}
	written = sampled(t, stream.Header, written, root)
	if source, err := os.ReadFile(expected.Source); err == nil {
		written = samples.WithoutMarkers(expected.Language, source, written)
	}
	if *update {
		rewrite(t, expected, written)

		return
	}
	for _, difference := range samples.Differences(expected.File, written) {
		t.Error(difference)
	}
}

// servedBridges keeps one process of each language's bridge up for the whole run, over one folder per language that
// every sample of it is placed in under its own construct's folder; each sample is still a request of its own, so no
// sample resolves against another.
type servedBridges struct {
	t      *testing.T
	roots  map[contract.Language]string
	askers map[contract.Language]bridge.Asker
}

func (s *servedBridges) of(language contract.Language) (string, bridge.Asker, error) {
	if asker, started := s.askers[language]; started {
		return s.roots[language], asker, nil
	}
	root, err := filepath.EvalSymlinks(s.t.TempDir())
	if err != nil {
		return "", nil, err
	}
	asker, err := bridgeOf[language].Serving([]string{root})
	if err != nil {
		return "", nil, err
	}
	s.roots[language], s.askers[language] = root, asker

	return root, asker, nil
}

func (s *servedBridges) close() {
	for _, asker := range s.askers {
		asker.Close()
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

func rewrite(t *testing.T, expected samples.Expected, written *contract.File) {
	t.Helper()
	written.Path, written.Module = expected.File.Path, expected.File.Module
	var tree bytes.Buffer
	if err := contract.Write(&tree, &contract.Stream{Header: expected.Header, Files: []*contract.File{written}}); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(expected.Tree, tree.Bytes(), 0o644); err != nil {
		t.Fatal(err)
	}
}

func sampled(t *testing.T, header contract.Header, file *contract.File, root string) *contract.File {
	t.Helper()
	var written bytes.Buffer
	if err := contract.Write(&written, &contract.Stream{Header: header, Files: []*contract.File{file}}); err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(strings.NewReader(strings.ReplaceAll(written.String(), root, "/sample")))
	if err != nil {
		t.Fatal(err)
	}

	return stream.Files[0]
}
