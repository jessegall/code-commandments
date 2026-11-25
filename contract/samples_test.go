package contract

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestSamplesEveryExpectedTreeReadsAndPointsIntoItsSource(t *testing.T) {
	trees, err := filepath.Glob("samples/testdata/expected/*/*.jsonl")
	if err != nil || len(trees) == 0 {
		t.Fatalf("no expected trees found: %v", err)
	}
	for _, tree := range trees {
		t.Run(strings.TrimPrefix(tree, "samples/testdata/expected/"), func(t *testing.T) {
			handle, err := os.Open(tree)
			if err != nil {
				t.Fatal(err)
			}
			defer handle.Close()
			stream, err := ReadAll(handle)
			if err != nil {
				t.Fatalf("the expected tree breaks the contract: %v", err)
			}
			if len(stream.Files) != 1 {
				t.Fatalf("an expected tree holds one file, not %d", len(stream.Files))
			}
			file := stream.Files[0]
			source, err := os.ReadFile(filepath.Join("samples/testdata", file.Path))
			if err != nil {
				t.Fatalf("%s names no source: %v", file.Path, err)
			}
			pointsInto(t, file, source)
		})
	}
}

func TestSamplesEveryConstructIsReadmeListed(t *testing.T) {
	readme, err := os.ReadFile("samples/testdata/expected/README.md")
	if err != nil {
		t.Fatal(err)
	}
	constructs, err := os.ReadDir("samples/testdata/expected")
	if err != nil {
		t.Fatal(err)
	}
	for _, construct := range constructs {
		if construct.IsDir() && !strings.Contains(string(readme), "| `"+construct.Name()+"` |") {
			t.Errorf("the README has no row for %s, saying which languages it holds and why not the others", construct.Name())
		}
	}
}

func pointsInto(t *testing.T, file *File, source []byte) {
	t.Helper()
	for node := range file.Nodes() {
		if node.Span.End > len(source) {
			t.Errorf("node %d ends at %d, past the file's %d bytes", node.ID, node.Span.End, len(source))
		}
		if line := lineAt(source, node.Span.Start); line != node.Span.Line {
			t.Errorf("node %d starts on line %d, not %d", node.ID, line, node.Span.Line)
		}
		parent, ok := node.Parent()
		if ok && (node.Span.Start < parent.Span.Start || node.Span.End > parent.Span.End) {
			t.Errorf("node %d %v lies outside its parent %d %v", node.ID, node.Span, parent.ID, parent.Span)
		}
	}
	for _, comment := range file.Comments {
		if comment.Span.End > len(source) {
			t.Fatalf("comment %d ends past the file", comment.ID)
		}
		if written := string(source[comment.Span.Start:comment.Span.End]); written != comment.Text {
			t.Errorf("comment %d's span holds %q, not its text %q", comment.ID, written, comment.Text)
		}
	}
}

func lineAt(source []byte, offset int) int {
	return strings.Count(string(source[:offset]), "\n") + 1
}
