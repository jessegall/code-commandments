package sync

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/walk"
)

var legacy = []string{
	"commandments-sins.md",
	".commandments/sins.md", ".commandments/.plan-active", ".commandments/.plan-stuck",
	".commandments/.plan-working-state", ".commandments/.plan-working-state.previous",
	".commandments/.plan-constraints", ".commandments/.constraints-verified", ".commandments/.plan-testing",
	".commandments/.judge-reminded", ".commandments/.remind-checklist",
}

func removeLegacyArtifacts(root string) {
	for _, file := range legacy {
		if walk.IsFile(filepath.Join(root, file)) {
			os.Remove(filepath.Join(root, file))
		}
	}

	checklists, _ := filepath.Glob(filepath.Join(root, ".commandments", "sins-*.md"))
	counters, _ := filepath.Glob(filepath.Join(root, ".commandments", ".*-count"))

	for _, path := range append(checklists, counters...) {
		os.Remove(path)
	}
}
