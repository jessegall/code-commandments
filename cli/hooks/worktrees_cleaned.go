package hooks

import (
	"github.com/jessegall/code-commandments/cli/untrack"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const cutEvent = "suites.coding.workers.worker_worktree.cut"

func WorktreeCut(line map[string]any) string {
	fields := eventFields(line)
	row, _ := fields["row"].(map[string]any)
	if row == nil {
		row = fields
	}

	var said []string
	if worktree := asText(row["folder"]); worktree != "" {
		if cleaned, err := untrack.Clean(worktree); err != nil {
			said = append(said, err.Error())
		} else if cleaned {
			said = append(said, worktree+" holds only the detectors in .commandments; it reads the config and "+
				"the cache at the project root")
		}
	}

	return Configured4(said)
}

func cleanEveryWorktree() {
	if project, journal := workspace.JournalProject(); journal {
		untrack.EveryWorktree(project)
	}
}
