package contract

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// fixtures is where a sample's /fixtures/ paths live in this repository.
const fixtures = "../tests/Fixtures/"

func TestEverySampleReadsAndPointsIntoItsFixture(t *testing.T) {
	samples, err := filepath.Glob("samples/*.jsonl")
	if err != nil || len(samples) == 0 {
		t.Fatalf("no samples found: %v", err)
	}
	for _, sample := range samples {
		t.Run(filepath.Base(sample), func(t *testing.T) {
			handle, err := os.Open(sample)
			if err != nil {
				t.Fatal(err)
			}
			defer handle.Close()
			stream, err := ReadAll(handle)
			if err != nil {
				t.Fatalf("the sample breaks the contract: %v", err)
			}
			for _, file := range stream.Files {
				source, err := os.ReadFile(fixtures + strings.TrimPrefix(file.Path, "/fixtures/"))
				if err != nil {
					t.Fatalf("%s names no fixture: %v", file.Path, err)
				}
				pointsInto(t, file, source)
			}
		})
	}
}

func pointsInto(t *testing.T, file *File, source []byte) {
	t.Helper()
	for _, node := range file.Nodes() {
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
