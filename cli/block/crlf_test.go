package block

import "strings"

import "testing"

// TestABlockIsFoundInADocumentWrittenWithCarriageReturns holds the markers to a file written on Windows. A
// document's lines may end in CRLF, and our own rule is that a file the user owns keeps its line endings, so
// the markers have to be recognised there too. If they are not, the block is taken for absent and appended
// again, and a project on Windows grows another copy of it with every sync.
func TestABlockIsFoundInADocumentWrittenWithCarriageReturns(t *testing.T) {
	document := "# Ours\r\n\r\nKeep this.\r\n\r\n" + Begin("test", "run it") + "\r\nold\r\n" + End("test") + "\r\n"

	written, found, err := Replace(document, "test", "new\n")
	if err != nil {
		t.Fatal(err)
	}
	if !found {
		t.Fatal("the block is not found in a document written with carriage returns, so sync appends another copy of it")
	}
	if strings.Contains(written, "old") {
		t.Error("the old content is still there")
	}
	if !strings.Contains(written, "Keep this.\r\n") {
		t.Error("the project's own carriage returns are gone")
	}
}
