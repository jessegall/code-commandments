package sync

import (
	"os"
	"path/filepath"
)

// legacy are files an earlier layout left at the project's top or loose in .commandments. The names are
// frozen on purpose: they exist only to be deleted, whatever the live layout becomes.
var legacy = []string{
	"commandments-sins.md",
	".commandments/sins.md", ".commandments/.plan-active", ".commandments/.plan-stuck",
	".commandments/.plan-working-state", ".commandments/.plan-working-state.previous",
	".commandments/.plan-constraints", ".commandments/.constraints-verified", ".commandments/.plan-testing",
	".commandments/.judge-reminded", ".commandments/.remind-checklist",
}

// removeLegacyArtifacts deletes every file of an earlier layout that is still there.
func removeLegacyArtifacts(root string) {
	for _, file := range legacy {
		if info, err := os.Stat(filepath.Join(root, file)); err == nil && info.Mode().IsRegular() {
			os.Remove(filepath.Join(root, file))
		}
	}

	checklists, _ := filepath.Glob(filepath.Join(root, ".commandments", "sins-*.md"))
	counters, _ := filepath.Glob(filepath.Join(root, ".commandments", ".*-count"))

	for _, path := range append(checklists, counters...) {
		os.Remove(path)
	}
}
