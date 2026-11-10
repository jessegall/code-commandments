package hooks

import (
	"os"
	"slices"
	"sync"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/workspace"
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

	letGoOfTheTreesOf(project)
	Warming(project, now)
}

// walkedUnder is the config file a project's tree was last read under and when that file was written.
type walkedUnder struct {
	mu     sync.Mutex
	stamps map[string]int64
	each   map[string]config.Config
}

var walked = walkedUnder{stamps: map[string]int64{}, each: map[string]config.Config{}}

func configFilesStamp(project string) int64 {
	return max(modified(workspace.Config(project)), modified(workspace.JSONConfig(project)))
}

func walkedTheTreeUnder(project string) {
	now, err := config.Load(project)
	if err != nil {
		return
	}

	walked.mu.Lock()
	defer walked.mu.Unlock()

	walked.stamps[project], walked.each[project] = configFilesStamp(project), now
}

func heldTheConfigFileOf(project string) {
	written := configFilesStamp(project)

	walked.mu.Lock()
	defer walked.mu.Unlock()

	if written == walked.stamps[project] {
		return
	}

	now, err := config.Load(project)
	if err != nil {
		return
	}

	was := walked.each[project]
	walked.stamps[project], walked.each[project] = written, now

	if walksTheSame(was, now) {
		return
	}

	letGoOfTheTreesOf(project)
	Warming(project, now)
}

func letGoOfTheTreesOf(project string) {
	for _, tree := range kept.Trees() {
		if tree.Project == project {
			kept.Drop(tree.Project, tree.Branch)
		}
	}
}

func walksTheSame(was, now config.Config) bool {
	return slices.Equal(was.Paths, now.Paths) &&
		slices.Equal(was.Excluded, now.Excluded) &&
		slices.Equal(was.JudgedDespiteExclusion(), now.JudgedDespiteExclusion())
}
