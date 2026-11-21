package scan

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/walk"
)

// A kept line stands on the tree it was written in: the same bridge's lines written under another contract version or
// another schema are kept in another folder and never served, whichever bridge wrote them.
func TestALineKeptUnderAnotherTreeIsNotServed(t *testing.T) {
	project := repository(t)
	journalDriven(t, project)
	held := Bridge{Kind: "php", Identity: func() string { return "the same bridge code" }}

	before := treeIdentity
	treeIdentity = func() string { return "tree-version-4" }
	old := linesKeptFor(held, []string{project})
	written(t, old.folder, "a-line.line", "{}")
	treeIdentity = before

	current := linesKeptFor(held, []string{project})
	if current.folder == old.folder {
		t.Fatalf("a line kept under the version 4 tree lies in the folder the current tree reads: %s", current.folder)
	}
	if walk.IsFile(filepath.Join(current.folder, "a-line.line")) {
		t.Error("the current tree is served a line written under the version 4 tree")
	}
	for _, bridge := range Bridges {
		if bridge.identity() == bridge.code() {
			t.Errorf("the %s bridge keys its lines on its code alone, so a new tree would be served its old lines", bridge.Kind)
		}
	}
}
