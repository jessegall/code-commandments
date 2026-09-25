package bridge

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func fixture(t *testing.T, path string) string {
	absolute, err := filepath.Abs(filepath.Join("../tests/Fixtures/python", path))
	if err != nil {
		t.Fatal(err)
	}

	return absolute
}

func TestTheMypyBridgeWritesAValidTreeOfThePythonFixture(t *testing.T) {
	stream, err := Once(TestMypy(t), fixture(t, ""))
	if err != nil {
		t.Fatal(err)
	}
	if stream.Header.Language != contract.Python {
		t.Errorf("the stream is %s, not python", stream.Header.Language)
	}
	if len(stream.Files) == 0 {
		t.Error("the stream holds no file")
	}
	if stream.Program == nil || len(stream.Program.Packages) == 0 {
		t.Error("the program line names no package")
	}
	code := 0
	for _, file := range stream.Files {
		inSourceOrder(t, file)
		for _, comment := range file.Comments {
			if comment.Extras != nil && comment.Extras.Python != nil && comment.Extras.Python.Code {
				code++
			}
		}
	}
	if code == 0 {
		t.Error("no comment is marked as code, not even label_queue's `# return self.labels[-1]`")
	}
}

// inSourceOrder holds every node of the file inside its parent, after the sibling before it.
func inSourceOrder(t *testing.T, file *contract.File) {
	t.Helper()
	for _, node := range file.Nodes() {
		parent, ok := node.Parent()
		if ok && (node.Span.Start < parent.Span.Start || node.Span.End > parent.Span.End) {
			t.Errorf("%s: node %d %v lies outside its parent %d %v", file.Path, node.ID, node.Span, parent.ID, parent.Span)
		}
		for at := 1; at < len(node.Children); at++ {
			if node.Children[at].Span.Start < node.Children[at-1].Span.End {
				t.Errorf("%s: node %d starts before its sibling %d ends", file.Path, node.Children[at].ID, node.Children[at-1].ID)
			}
		}
	}
}

func TestAServedBridgeAnswersEachRequestWithAWholeStream(t *testing.T) {
	server, err := Serve(TestMypy(t))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	cli := fixture(t, "shop/cli.py")
	first, err := server.Ask(Request{Paths: []string{fixture(t, "")}, Write: []string{cli}})
	if err != nil {
		t.Fatal(err)
	}
	for _, file := range first.Files {
		if file.Context == (file.Path == cli) {
			t.Errorf("%s: context is %v", file.Path, file.Context)
		}
	}
	second, err := server.Ask(Request{Paths: []string{cli}})
	if err != nil {
		t.Fatal(err)
	}
	if len(second.Files) != 1 || second.Files[0].Context {
		t.Errorf("the second answer holds %d files", len(second.Files))
	}
}
