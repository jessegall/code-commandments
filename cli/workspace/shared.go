package workspace

import (
	"crypto/sha256"
	"encoding/hex"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/walk"
)

// SharedFolder is the one folder a project and every worktree of it share between runs: the journal's folder for the
// plugin when the journal drives the project, and a folder under the user's cache otherwise; empty for no project.
func SharedFolder(project string) string {
	if project == "" {
		return ""
	}

	if main := git.ProjectRoot(project); main != "" {
		project = main
	}

	if space := At(project, ""); space.IsJournalDriven() {
		return space.StateDir()
	}

	ours, err := cache.Ours()
	if err != nil {
		return ""
	}
	named := sha256.Sum256([]byte(project))

	return filepath.Join(ours, "remembered", hex.EncodeToString(named[:8]))
}

// ConsumerRoot is the project a command run in cwd works on: the nearest folder above it holding a composer.json, short
// of the home folder, else its git checkout.
func ConsumerRoot(cwd string) string {
	home, _ := os.UserHomeDir()

	if real, err := walk.Resolved(cwd); err == nil {
		cwd = real
	}

	for dir := cwd; dir != filepath.Dir(dir) && dir != home; dir = filepath.Dir(dir) {
		if info, err := os.Stat(filepath.Join(dir, "composer.json")); err == nil && info.Mode().IsRegular() {
			return dir
		}
	}

	return git.Root(cwd)
}
