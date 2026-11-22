package engine

import (
	"strings"
	"testing"
)

func TestACommentDocumentsOnlyTheNodeItsBridgeAttachedItTo(t *testing.T) {
	file := strings.Replace(versionFiveReader, `"comments": []`, `"comments": [
		{"id": 0, "form": "doc", "text": "/** The reader's licence, above a blank line. */", "span": [0, 8, 1]},
		{"id": 1, "form": "doc", "text": "/** Reads the stock. */", "span": [9, 10, 1], "attached": 2}]`, 1)
	codebase := versionFiveCodebase(t, file)
	runs := codebase.CommentRuns()
	if len(runs) != 2 {
		t.Fatalf("read %d comment runs, not the licence and the doc comment", len(runs))
	}

	if documented := runs[0].Documents(); documented.Exists() {
		t.Errorf("the licence no bridge attached documents the %s at line %d", documented.Kind(), documented.Line())
	}
	if documented := runs[1].Documents(); documented.Kind() != "function" {
		t.Errorf("the attached doc comment documents %q, not the function it is attached to", documented.Kind())
	}
}
