package git

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
)

var inProgress = []string{"index.lock", "MERGE_HEAD", "CHERRY_PICK_HEAD", "REVERT_HEAD", "rebase-merge", "rebase-apply", "BISECT_LOG"}

func UndoLeavingOut(checkout string, paths ...string) (bool, error) {
	gitdir := gitDirOf(checkout)
	if gitdir == "" {
		return false, nil
	}

	patterns := filepath.Join(gitdir, "info", "sparse-checkout")

	written, err := os.ReadFile(patterns)
	if err != nil {
		return false, nil
	}

	ours := []string{"/*"}
	for _, path := range paths {
		ours = append(ours, "!/"+strings.TrimLeft(path, "/"))
	}

	if !slices.Equal(strings.Fields(string(written)), ours) {
		return false, nil
	}

	if said, err := wrote(checkout, "sparse-checkout", "disable"); err != nil {
		return false, fmt.Errorf("%s still leaves %s out: %s", checkout, strings.Join(paths, ", "), said)
	}

	return true, os.Remove(patterns)
}

func TrackedIn(checkout, folder string) []string {
	return nulSeparated(run(checkout, "ls-files", "-z", "--", folder))
}

func CommittedIn(checkout, folder string) []string {
	return nulSeparated(run(checkout, "ls-tree", "-r", "-z", "--name-only", "HEAD", "--", folder))
}

func KeepOffDisk(checkout, folder string, kept func(path string) bool) error {
	var unmarked []string

	for _, entry := range nulSeparated(run(checkout, "ls-files", "-v", "-z", "--", folder)) {
		tag, path, tagged := strings.Cut(entry, " ")
		if tagged && tag != "S" && !kept(path) {
			unmarked = append(unmarked, path)
		}
	}

	if len(unmarked) == 0 {
		return nil
	}

	if said, err := literally(checkout, nil, unmarked, "update-index", "--skip-worktree", "-z", "--stdin"); err != nil {
		return fmt.Errorf("git in %s would see %s deleted: %s", checkout, strings.Join(unmarked, ", "), said)
	}

	return nil
}

func StopTracking(root string, paths []string) (string, error) {
	return untracking(root, nil, paths)
}

func CommitStoppedTracking(root, message string, committed []string) (bool, error) {
	head := ask(root, "rev-parse", "--verify", "-q", "HEAD")
	if head == "" || len(committed) == 0 || isBusy(root) {
		return false, nil
	}

	folder, err := os.MkdirTemp("", "commandments-untrack-")
	if err != nil {
		return false, err
	}
	defer os.RemoveAll(folder)

	apart := []string{"GIT_INDEX_FILE=" + filepath.Join(folder, "index")}
	if said, err := literally(root, apart, nil, "read-tree", head); err != nil {
		return false, fmt.Errorf("git read-tree: %s", said)
	}
	if said, err := untracking(root, apart, committed); err != nil {
		return false, fmt.Errorf("git rm --cached: %s", said)
	}

	tree, err := literally(root, apart, nil, "write-tree")
	if err != nil {
		return false, fmt.Errorf("git write-tree: %s", tree)
	}

	commit, err := wrote(root, "commit-tree", tree, "-p", head, "-m", message)
	if err != nil {
		return false, fmt.Errorf("git commit-tree: %s", commit)
	}

	if said, err := wrote(root, "update-ref", "-m", message, "HEAD", commit, head); err != nil {
		return false, fmt.Errorf("git update-ref: %s", said)
	}

	if said, err := untracking(root, nil, committed); err != nil {
		wrote(root, "update-ref", "-m", "undo: "+message, "HEAD", head, commit)

		return false, fmt.Errorf("git rm --cached: %s", said)
	}

	return true, nil
}

func IsAWorktree(checkout string) bool {
	gitdir := gitDirOf(checkout)

	return gitdir != "" && filepath.Base(filepath.Dir(gitdir)) == worktrees
}

func isBusy(root string) bool {
	gitdir := ask(root, "rev-parse", "--absolute-git-dir")
	if gitdir == "" {
		return true
	}

	return slices.ContainsFunc(inProgress, func(name string) bool {
		_, err := os.Lstat(filepath.Join(gitdir, name))

		return err == nil
	})
}

func untracking(root string, env, paths []string) (string, error) {
	return literally(root, env, paths, "rm", "--cached", "-q", "-r", "--ignore-unmatch", "--pathspec-from-file=-", "--pathspec-file-nul")
}

func literally(dir string, env, paths []string, args ...string) (string, error) {
	counted()

	command := exec.Command("git", append([]string{"-C", dir}, args...)...)
	command.Env = append(append(os.Environ(), "GIT_LITERAL_PATHSPECS=1"), env...)
	if paths != nil {
		command.Stdin = strings.NewReader(strings.Join(paths, "\x00") + "\x00")
	}

	out, err := command.CombinedOutput()

	return strings.TrimSpace(string(out)), err
}

func nulSeparated(listed string) []string {
	var paths []string

	for _, path := range strings.Split(listed, "\x00") {
		if path != "" {
			paths = append(paths, path)
		}
	}

	return paths
}
