package git

import (
	"fmt"
	"path/filepath"
	"strings"
)

// LeaveOutOfCheckout takes that folder off this checkout's disk without git seeing a deletion, through the
// checkout's own sparse-checkout, which is per worktree.
//
// It exists for one folder: a worktree carries a copy of .commandments on its branch, which nothing reads — the
// config and the project's own rules always come from the main checkout of the repository — but which anyone who
// opens the worktree takes for the rules in force there.
//
// NOT by removing it. The folder is TRACKED: the journal commits a worker's changes when it hands in, so a
// deletion here would be committed and the merge would take the project's own rules with it on the main branch.
// Sparse-checkout leaves the file out of the working tree while git still holds it in the index, so a commit
// made in this checkout carries it untouched.
//
// A checkout that already has sparse-checkout patterns of its own is left alone and said so: its patterns are
// somebody's choice, and overwriting them would take away files the work needs.
func LeaveOutOfCheckout(checkout, folder string) error {
	if !IsAWorktree(checkout) {
		return fmt.Errorf("%s is no worktree of a repository, so nothing of it is left out", checkout)
	}

	if patterns := strings.TrimSpace(run(checkout, "sparse-checkout", "list")); patterns != "" {
		return fmt.Errorf("%s already leaves files out of its checkout, which is somebody's choice: %s",
			checkout, strings.Join(strings.Fields(patterns), " "))
	}

	// Every path in, that one out, and no cone: a cone can only name folders to KEEP, which would be the whole
	// tree written out as patterns and would go stale as the project grows.
	said, err := wrote(checkout, "sparse-checkout", "set", "--no-cone", "/*", "!/"+strings.Trim(folder, "/")+"/")
	if err != nil {
		return fmt.Errorf("%s could not be left out of %s: %s", folder, checkout, said)
	}

	return nil
}

// IsAWorktree says whether the folder is a git worktree: its .git is a file naming a worktree of a repository,
// where a repository of its own has a .git folder and a submodule's file names a module.
func IsAWorktree(checkout string) bool {
	pointer := run(checkout, "rev-parse", "--git-dir")

	return strings.Contains(filepath.ToSlash(strings.TrimSpace(pointer)), "/worktrees/")
}
