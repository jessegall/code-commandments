package judge

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	pluginData = "JOURNAL_PLUGIN_DATA"

	rememberedDetectorsFile = "detector-memories.json"
	rememberedWeighingsFile = "csharp-weighings.json"
)

// Remembered is what one run keeps for the next in its project's own folder, good only for the build that wrote it: what every detector answered for each file's contents, and what the aggregating C# rules found. It lives at the journal of the project root when the journal drives the project, and under the user's cache otherwise: one folder per project that all its worktrees share, so the plugin's runs, queued runs and runs started by hand remember together and claim the same project. Finding it also hands on the hours the project keeps an unused cache, so the run and every bridge it starts prune by them.
type Remembered struct {
	Memories  detectors.MemoryFile
	Weighings WeighingsFile
}

func RememberedFor(project string, warn func(...string)) (Remembered, bool) {
	if chosen, err := config.Load(project); err == nil {
		cache.KeepFor(chosen.CacheHours)
	}

	folder := rememberedFolder(project)
	if folder == "" {
		return Remembered{}, false
	}

	if space := workspace.At(project, ""); space.IsJournalDriven() {
		cache.KeepIn(folder)
	}

	return Remembered{
		Memories:  detectors.MemoryFile{Memories: detectors.EveryMemory(), Path: filepath.Join(folder, rememberedDetectorsFile), Build: bridge.Build(), Warn: warn},
		Weighings: WeighingsFile{Path: filepath.Join(folder, rememberedWeighingsFile), Build: bridge.Build(), Warn: warn},
	}, true
}

func rememberedFolder(project string) string {
	return workspace.SharedFolder(project)
}

func (r Remembered) Load() {
	r.Memories.Load()
	r.Weighings.Load()
}

func (r Remembered) Save() {
	r.Memories.Save()
	r.Weighings.Save()
}
