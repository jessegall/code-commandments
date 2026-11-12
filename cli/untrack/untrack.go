package untrack

import (
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const (
	folder = ".commandments"

	kept = ".gitignore"

	untrackedMessage = "Stop tracking code-commandments state: the journal serves the config from the project root"
)

var leftOutByAnOldRelease = folder + "/config.json"

func Everywhere(root string) []string {
	var said []string

	if untracked := TheState(root); untracked.Said != "" {
		said = append(said, untracked.Said)
	}

	return append(said, EveryWorktree(root)...)
}

// Untracked is what became of the state a project had committed in its .commandments folder: the files stay on the main checkout's disk, and git stops following them, so no checkout carries a copy of the config the journal serves from the project root. Only the detectors in custom/ stay tracked.
type Untracked struct {
	Said string
	Done bool
}

func TheState(root string) Untracked {
	if git.Root(root) == "" {
		return Untracked{}
	}

	if workspace.At(root, "").IsJournalDriven() {
		return committed(root, stateIn(git.CommittedIn(root, folder)))
	}

	tracked := stateIn(git.TrackedIn(root, folder))
	if len(tracked) == 0 {
		return Untracked{}
	}

	if out, err := git.StopTracking(root, tracked); err != nil {
		return Untracked{Said: "the config could not be untracked (" + out +
			"), so a worktree of this project still carries a copy of it"}
	}

	return Untracked{
		Said: "⇡ " + strings.Join(tracked, ", ") + " no longer tracked: its settings come from the project root, and " +
			"a committed copy travels into every worktree. The files themselves are untouched.",
		Done: true,
	}
}

func committed(root string, tracked []string) Untracked {
	done, err := git.CommitStoppedTracking(root, untrackedMessage, tracked)
	if err != nil {
		return Untracked{Said: "the state in " + folder + " could not be untracked: " + err.Error()}
	}

	if !done {
		return Untracked{}
	}

	return Untracked{
		Said: "⇡ committed: " + strings.Join(tracked, ", ") + " no longer tracked, since the journal serves the " +
			"config from the project root. The files are still on the main checkout's disk.",
		Done: true,
	}
}

func EveryWorktree(root string) []string {
	main := git.ProjectRoot(root)
	if main == "" {
		return nil
	}

	cleans := workspace.At(main, "").IsJournalDriven()

	var said []string

	for _, worktree := range git.Worktrees(main) {
		if !git.IsAWorktree(worktree) {
			continue
		}

		if undone, err := git.UndoLeavingOut(worktree, leftOutByAnOldRelease); err != nil {
			said = append(said, err.Error())
		} else if undone {
			said = append(said, "⇡ "+worktree+" no longer leaves "+leftOutByAnOldRelease+" out of its checkout")
		}

		if !cleans {
			continue
		}

		if cleaned, err := Clean(worktree); err != nil {
			said = append(said, err.Error())
		} else if cleaned {
			said = append(said, "⇡ "+worktree+" holds only the detectors in "+folder+"; it reads the config "+
				"and the cache at the project root")
		}
	}

	return said
}

func Clean(worktree string) (bool, error) {
	if !git.IsAWorktree(worktree) {
		return false, nil
	}

	held := filepath.Join(worktree, folder)
	if info, err := os.Lstat(held); err != nil || !info.IsDir() {
		return false, nil
	}

	entries, err := os.ReadDir(held)
	if err != nil {
		return false, nil
	}

	var leftover []string
	for _, entry := range entries {
		if !isKept(entry.Name()) {
			leftover = append(leftover, entry.Name())
		}
	}

	if len(leftover) == 0 {
		return false, nil
	}

	if err := git.KeepOffDisk(worktree, folder, isKeptPath); err != nil {
		return false, err
	}

	for _, name := range leftover {
		if err := os.RemoveAll(filepath.Join(held, name)); err != nil {
			return false, err
		}
	}

	return true, nil
}

func stateIn(tracked []string) []string {
	return slices.DeleteFunc(tracked, isKeptPath)
}

func isKeptPath(path string) bool {
	inside, under := strings.CutPrefix(path, folder+"/")
	if !under {
		return true
	}

	return isKept(strings.SplitN(inside, "/", 2)[0])
}

func isKept(name string) bool {
	return name == kept || name == workspace.Custom
}
