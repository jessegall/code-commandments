package untrack

import (
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func Everywhere(root string) []string {
	var said []string

	if untracked := TheConfig(root); untracked.Said != "" {
		said = append(said, untracked.Said)
	}

	return append(said, OutOfEveryWorktree(root)...)
}

// Untracked is what became of a config a project had committed: the file stays on disk with the project's own
// settings in it, and git is told to stop following it, so no checkout of that project carries a copy its
// worktrees can read instead of the journal's own settings.
type Untracked struct {
	Said string
	Done bool
}

func TheConfig(root string) Untracked {
	config := workspace.JSONConfig(workspace.SettingsRoot(root))

	if git.Root(root) == "" {
		return Untracked{}
	}

	under, inside := under(config, root)
	if !inside {
		return Untracked{}
	}

	if !git.Tracks(root, under) {
		return Untracked{}
	}

	out, err := git.StopTracking(root, under)
	if err != nil {
		return Untracked{Said: "the config could not be untracked (" + out +
			"), so a worktree of this project still carries a copy of it"}
	}

	return Untracked{
		Said: "⇡ " + under + " is no longer tracked: its settings come from the journal, and a committed copy " +
			"travels into every worktree where it is read instead of them. The file itself is untouched.",
		Done: true,
	}
}

func under(path, root string) (string, bool) {
	relative, err := filepath.Rel(strings.TrimRight(root, "/"), path)
	if err != nil || strings.HasPrefix(relative, "..") {
		return "", false
	}

	return filepath.ToSlash(relative), true
}

func OutOfEveryWorktree(root string) []string {
	if git.Root(root) == "" {
		return nil
	}

	under, inside := under(workspace.JSONConfig(workspace.SettingsRoot(root)), root)
	if !inside {
		return nil
	}

	var said []string

	for _, worktree := range git.Worktrees(root) {
		if !git.IsAWorktree(worktree) || !git.Tracks(worktree, under) || !onDisk(worktree, under) {
			continue
		}

		if err := git.LeaveOutOfCheckout(worktree, under); err != nil {
			said = append(said, "the config is still on the disk of a worktree: "+err.Error())

			continue
		}

		said = append(said, "⇡ "+under+" is off the disk of "+worktree+", so it reads the journal's settings; "+
			"git sees no deletion, so nothing handed in from it takes the config with it.")
	}

	return said
}

func onDisk(checkout, under string) bool {
	_, err := os.Stat(filepath.Join(checkout, filepath.FromSlash(under)))

	return err == nil
}
