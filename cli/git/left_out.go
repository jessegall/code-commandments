package git

import (
	"fmt"
	"path/filepath"
	"slices"
	"strings"
)

func LeaveOutOfCheckout(checkout string, paths ...string) error {
	if !IsAWorktree(checkout) {
		return fmt.Errorf("%s is no worktree of a repository, so nothing of it is left out", checkout)
	}

	wanted := []string{"/*"}
	for _, path := range paths {
		wanted = append(wanted, "!/"+strings.TrimLeft(path, "/"))
	}

	if patterns := strings.Fields(run(checkout, "sparse-checkout", "list")); len(patterns) > 0 {
		if slices.Equal(patterns, wanted) {
			return nil
		}

		return fmt.Errorf("%s already leaves files out of its checkout, which is somebody's choice: %s",
			checkout, strings.Join(patterns, " "))
	}

	said, err := wrote(checkout, append([]string{"sparse-checkout", "set", "--no-cone"}, wanted...)...)
	if err != nil {
		return fmt.Errorf("%s could not be left out of %s: %s", strings.Join(paths, ", "), checkout, said)
	}

	return nil
}

func IsAWorktree(checkout string) bool {
	pointer := run(checkout, "rev-parse", "--git-dir")

	return strings.Contains(filepath.ToSlash(strings.TrimSpace(pointer)), "/worktrees/")
}
