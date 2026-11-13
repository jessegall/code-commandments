package workspace

import (
	"crypto/sha256"
	"encoding/hex"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cli/git"
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

	cache, err := bundle.CacheFolder()
	if err != nil {
		return ""
	}
	named := sha256.Sum256([]byte(project))

	return filepath.Join(cache, "code-commandments", "remembered", hex.EncodeToString(named[:8]))
}
