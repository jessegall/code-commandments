package git

import (
	"fmt"
	"path/filepath"
	"strings"
)

func LeaveOutOfCheckout(checkout, folder string) error {
	if !IsAWorktree(checkout) {
		return fmt.Errorf("%s is no worktree of a repository, so nothing of it is left out", checkout)
	}

	if patterns := strings.TrimSpace(run(checkout, "sparse-checkout", "list")); patterns != "" {
		return fmt.Errorf("%s already leaves files out of its checkout, which is somebody's choice: %s",
			checkout, strings.Join(strings.Fields(patterns), " "))
	}

	said, err := wrote(checkout, "sparse-checkout", "set", "--no-cone", "/*", "!/"+strings.Trim(folder, "/")+"/")
	if err != nil {
		return fmt.Errorf("%s could not be left out of %s: %s", folder, checkout, said)
	}

	return nil
}

func IsAWorktree(checkout string) bool {
	pointer := run(checkout, "rev-parse", "--git-dir")

	return strings.Contains(filepath.ToSlash(strings.TrimSpace(pointer)), "/worktrees/")
}
