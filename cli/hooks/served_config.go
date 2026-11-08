package hooks

import (
	"os"
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
)

func heldTheConfigOf(line map[string]any) {
	block, _ := line["config"].(map[string]any)
	version := intOf(block["version"])

	project, journal := os.Getenv(journalRoot), journalBinary()
	if version == 0 || project == "" || journal == "" {
		return
	}

	was, now, moved := config.TheJournalHolds(project, version, config.FetchedFromTheJournal(journal, project))
	if !moved || walksTheSame(was, now) {
		return
	}

	for _, tree := range kept.Trees() {
		if tree.Project == project {
			kept.Drop(tree.Project, tree.Branch)
		}
	}

	Warming(project, now)
}

func walksTheSame(was, now config.Config) bool {
	return slices.Equal(was.Paths, now.Paths) &&
		slices.Equal(was.Excluded, now.Excluded) &&
		slices.Equal(was.JudgedDespiteExclusion(), now.JudgedDespiteExclusion())
}
