package hooks

import (
	"os/exec"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestAHandInMeasuredFromBeforeAMergeCountsTheMergesLinesAndFromTheMergeDoesNot(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	worktree := workerBranchOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/base.py":               "def base(order) -> int:\n    return order.total\n",
	}, map[string]string{
		"src/ship.py": "def ship(order) -> str:\n    return order.carrier\n",
	})
	project := projectOf(worktree)
	before := strings.TrimSpace(gitOut(t, worktree, "merge-base", "trunk", "HEAD"))

	wrote(t, project, map[string]string{"src/merged.py": sinful})
	gitIn(t, project, "add", "-A")
	gitIn(t, project, "commit", "-q", "-m", "a sin the project wrote after the cut")
	gitIn(t, worktree, "merge", "-q", "--no-commit", "--no-ff", "trunk")

	settings, err := config.Load(worktree)
	if err != nil {
		t.Fatal(err)
	}
	rules, err := settings.Enabled(config.InstalledIn(worktree))
	if err != nil {
		t.Fatal(err)
	}
	settings = settings.Judging(rules)

	work := merging{worker: "worker 12", worktree: worktree, base: "trunk", files: []string{"src/merged.py"}}

	work.since = before
	if stale := work.shallow(settings, rules); len(stale) == 0 {
		t.Fatal("measured from before the merge, the merged sin was not counted: the test proves nothing")
	}

	work.since = git.MergeBase(worktree, "trunk")
	if now := work.shallow(settings, rules); len(now) != 0 {
		t.Errorf("measured from the merge being made, the worker was held for %d sins the merge brought in", len(now))
	}
}

func gitOut(t *testing.T, root string, args ...string) string {
	t.Helper()

	out, err := exec.Command("git", append([]string{"-C", root}, args...)...).Output()
	if err != nil {
		t.Fatalf("git %s: %v", strings.Join(args, " "), err)
	}

	return string(out)
}
