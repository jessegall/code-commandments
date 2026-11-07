package sync

import (
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Untracked is what became of a config a project had committed: the file stays on disk with the project's own
// settings in it, and git is told to stop following it, so no checkout of that project carries a copy its
// worktrees can read instead of the journal's own settings.
type Untracked struct {
	Said string
	Done bool
}

func UntrackTheConfig(root string) Untracked {
	config := workspace.JSONConfig(workspace.SettingsRoot(root))

	if git.Root(root) == "" {
		return Untracked{}
	}

	under, inside := under(config, root)
	if !inside {
		return Untracked{}
	}

	if !tracked(root, under) {
		return Untracked{}
	}

	out, err := exec.Command("git", "-C", root, "rm", "--cached", "-q", "--", under).CombinedOutput()
	if err != nil {
		return Untracked{Said: "the config could not be untracked (" + strings.TrimSpace(string(out)) +
			"), so a worktree of this project still carries a copy of it"}
	}

	return Untracked{
		Said: "⇡ " + under + " is no longer tracked: its settings come from the journal, and a committed copy " +
			"travels into every worktree where it is read instead of them. The file itself is untouched.",
		Done: true,
	}
}

func tracked(root, under string) bool {
	out, err := exec.Command("git", "-C", root, "ls-files", "--error-unmatch", "--", under).CombinedOutput()

	return err == nil && strings.TrimSpace(string(out)) != ""
}

func under(path, root string) (string, bool) {
	relative, err := filepath.Rel(strings.TrimRight(root, "/"), path)
	if err != nil || strings.HasPrefix(relative, "..") {
		return "", false
	}

	return filepath.ToSlash(relative), true
}
