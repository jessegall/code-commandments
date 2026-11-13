package scan

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

// The lines of a bridge's build are kept in a folder of their own: a new build moves every line of the builds before
// it aside and deletes them, since none of them can stand again, and keeps its own.
func TestANewBuildOfABridgeDeletesTheLinesOfTheBuildsBefore(t *testing.T) {
	project := repository(t)
	journalDriven(t, project)
	kind := filepath.Join(workspace.SharedFolder(project), "lines", "mypy")
	written(t, kind, "an-old-build.line", "{}")

	first := Bridge{Kind: "mypy", Identity: func() string { return "the first build" }}
	kept := linesKeptFor(first, []string{project})
	if !walk.IsFolder(kept.folder) {
		t.Fatalf("the first build keeps no folder of its own: %q", kept.folder)
	}
	removeOtherBuilds(kind)
	if walk.IsFile(filepath.Join(kind, "an-old-build.line")) {
		t.Errorf("a line of a build before still lies beside the first build's folder")
	}
	if aside, _ := filepath.Glob(kind + otherBuilds + "*"); len(aside) != 0 {
		t.Errorf("the lines moved aside were not deleted: %v", aside)
	}

	written(t, kept.folder, "a-line-of-the-first-build.line", "{}")
	if again := linesKeptFor(first, []string{project}); again.folder != kept.folder || !walk.IsFile(filepath.Join(kept.folder, "a-line-of-the-first-build.line")) {
		t.Errorf("a second run of the same build lost its own lines")
	}

	second := linesKeptFor(Bridge{Kind: "mypy", Identity: func() string { return "the second build" }}, []string{project})
	removeOtherBuilds(kind)
	if second.folder == kept.folder || walk.IsFolder(kept.folder) {
		t.Errorf("the second build kept the first build's lines at %s beside its own at %s", kept.folder, second.folder)
	}
	if _, err := os.Stat(second.folder); err != nil {
		t.Errorf("the second build has no folder of its own: %v", err)
	}
}
