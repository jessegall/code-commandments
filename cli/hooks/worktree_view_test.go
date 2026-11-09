package hooks

import (
	"path/filepath"
	"slices"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/warm"
)

func worktreeOverAHeldTree(t *testing.T) (root, worktree string, named branchRoot, base *warm.Base) {
	t.Helper()

	root, _ = workerProject(t)
	wroteInto(t, filepath.Join(root, "src", "shared.py"), "VERSION = 1\n")
	committed(t, root, "the file both checkouts hold")

	worktree = stressWorktreeIn(root, 17)
	cutInto(t, root, worktree, "feature/own-view")

	settings, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	warmFor(t, root, settings)

	named, _ = rootOf(root)

	base, held := kept.Holds(named.project, named.branch)
	if !held {
		t.Fatal("the project's tree was not held")
	}

	return root, worktree, named, base
}

func viewOverlaid(t *testing.T, worktree string, base *warm.Base, named branchRoot) func(string) bool {
	t.Helper()

	view, why := overlaid(kept.Over(worktree), base, named, nil, nil, nil, time.Now())
	if why != "" {
		t.Fatalf("the worktree's view could not be laid: %s", why)
	}

	return func(file string) bool { return holds(view, file) }
}

func TestAWorktreeIsNotJudgedAgainstWhatMainCommittedAfterItsCut(t *testing.T) {
	root, worktree, named, base := worktreeOverAHeldTree(t)

	shared, newer := filepath.Join(root, "src", "shared.py"), filepath.Join(root, "src", "newer.py")
	wroteInto(t, shared, "VERSION = 2\n")
	wroteInto(t, newer, "ADDED = 1\n")
	committed(t, root, "main moves on after the cut")

	patched, _ := taken(base, named, []string{shared, newer})
	own := filepath.Join(worktree, "src", "shared.py")

	differs, covered := differingFrom(patched, named, worktree, nil)
	if !covered {
		t.Fatal("the worktree shares history with its branch, yet was not covered")
	}
	if !slices.Contains(differs.Own, own) {
		t.Errorf("the served judge does not read the worktree's own copy of what main changed: %v", differs.Own)
	}
	if !slices.Contains(differs.Gone, newer) {
		t.Errorf("the served judge keeps a file only main has: %v", differs.Gone)
	}

	inView := viewOverlaid(t, worktree, patched, named)
	if !inView(own) || inView(shared) {
		t.Errorf("the view holds worktree copy %v, main's copy %v", inView(own), inView(shared))
	}
	if inView(newer) {
		t.Error("the view holds a file only main has")
	}
}

func TestAWorktreeDoesNotTakeAnEditTheProjectMadeAfterItsViewWasLaid(t *testing.T) {
	root, worktree, named, base := worktreeOverAHeldTree(t)

	own := filepath.Join(worktree, "src", "shared.py")
	viewOverlaid(t, worktree, base, named)

	if over := kept.Over(worktree); !over.Empty() {
		t.Fatalf("a worktree identical to its branch holds %d files of its own, so this test would prove nothing", over.Count())
	}

	shared, onlyHere := filepath.Join(root, "src", "shared.py"), filepath.Join(root, "src", "only_in_the_project.py")
	wroteInto(t, shared, "VERSION = 3\n")
	wroteInto(t, onlyHere, "ADDED = 1\n")

	differs, covered := differingFrom(base, named, worktree, nil)
	if !covered {
		t.Fatal("the worktree shares history with its branch, yet was not covered")
	}
	if slices.Contains(differs.Moved, shared) {
		t.Errorf("the served judge reads the project's edit into a worktree: %v", differs.Moved)
	}
	if !slices.Contains(differs.Own, own) {
		t.Errorf("the served judge does not read the worktree's copy of the project's edit: %v", differs.Own)
	}
	if !slices.Contains(differs.Gone, onlyHere) {
		t.Errorf("the served judge keeps a file only the project has: %v", differs.Gone)
	}

	patched, _ := taken(base, named, []string{shared, onlyHere})

	inView := viewOverlaid(t, worktree, patched, named)
	if !inView(own) || inView(shared) {
		t.Errorf("the view holds worktree copy %v, the project's edit %v", inView(own), inView(shared))
	}
	if inView(onlyHere) {
		t.Error("the view holds a file only the project has")
	}
}
