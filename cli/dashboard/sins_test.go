package dashboard

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
)

// TestTheDashboardKeepsOnlyWhatThisProjectJudges holds the store to the project it belongs to: a judge of another
// tree, a worktree in a temporary folder, and the samples a project's own rules are proven on all find real sins,
// and none of them is this project's to show. A finding this project will never judge again is one nothing could
// ever clear, so it would sit on the dashboard for good.
func TestTheDashboardKeepsOnlyWhatThisProjectJudges(t *testing.T) {
	root := t.TempDir()
	if real, err := filepath.EvalSymlinks(root); err == nil {
		root = real
	}

	elsewhere := t.TempDir()
	if real, err := filepath.EvalSymlinks(elsewhere); err == nil {
		elsewhere = real
	}

	mine := filepath.Join(root, "src", "order.py")
	sample := filepath.Join(workspace.SamplesDir(root), "plain_code_words.py")
	theirs := filepath.Join(elsewhere, "src", "other.py")

	for _, file := range []string{mine, sample, theirs} {
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	space := workspace.At(root, "")
	findings := []engine.Finding{
		{Sin: "dict-bag", Skill: "python/value-objects", File: mine, Location: mine + ":1"},
		{Sin: "dict-bag", Skill: "python/value-objects", File: sample, Location: sample + ":1"},
		{Sin: "dict-bag", Skill: "python/value-objects", File: theirs, Location: theirs + ":1"},
	}

	if err := Record(space, findings, map[string]bool{mine: true, sample: true, theirs: true}); err != nil {
		t.Fatal(err)
	}

	var kept []string
	for _, stored := range storedIn(space) {
		kept = append(kept, stored.Path)
	}
	if !slices.Contains(kept, mine) {
		t.Errorf("the project's own finding is not kept: %v", kept)
	}

	for _, path := range []string{sample, theirs} {
		if slices.Contains(kept, path) {
			t.Errorf("%s is on the dashboard, and this project will never judge it again", path)
		}
	}
}
