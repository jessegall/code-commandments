package judge

import (
	"crypto/sha256"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	pluginData = "JOURNAL_PLUGIN_DATA"

	rememberedDetectorsFile = "detector-memories.json"
	rememberedWeighingsFile = "csharp-weighings.json"
	readingFile             = "reading"
)

// Remembered is what one run keeps for the next in its project's own folder, good only for the build that wrote it: what every detector answered for each file's contents, and what the aggregating C# rules found. It lives under the user's cache, one folder per project that all its worktrees share, so the plugin's runs, queued runs and runs started by hand remember together and claim the same project. Finding it also hands on the hours the project keeps an unused cache, so the run and every bridge it starts prune by them. Only one run reads a project at a time: a run claims the project while it reads, and a second run that finds it claimed stops at once rather than waiting; the system lets go of the claim the moment its run ends, so a run stopped with ^C or killed blocks nobody.
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

	return Remembered{
		Memories:  detectors.MemoryFile{Memories: detectors.EveryMemory(), Path: filepath.Join(folder, rememberedDetectorsFile), Build: Build(), Warn: warn},
		Weighings: WeighingsFile{Path: filepath.Join(folder, rememberedWeighingsFile), Build: Build(), Warn: warn},
	}, true
}

func rememberedFolder(project string) string {
	cache, err := bundle.CacheFolder()
	if err != nil || project == "" {
		return ""
	}

	if main := git.ProjectRoot(project); main != "" {
		project = main
	}
	named := sha256.Sum256([]byte(project))

	return filepath.Join(cache, "code-commandments", "remembered", hex.EncodeToString(named[:8]))
}

func (r Remembered) ReadAlone() (release func(), alone bool) {
	return atomic.ClaimAt(filepath.Join(filepath.Dir(r.Memories.Path), readingFile)).Take()
}

func (r Remembered) Load() {
	r.Memories.Load()
	r.Weighings.Load()
}

func (r Remembered) Save() {
	r.Memories.Save()
	r.Weighings.Save()
}

func Build() string {
	path, err := os.Executable()
	if err != nil {
		return bridge.Release
	}

	file, err := os.Open(path)
	if err != nil {
		return bridge.Release
	}
	defer file.Close()

	digest := sha256.New()
	if _, err := io.Copy(digest, file); err != nil {
		return bridge.Release
	}

	return bridge.Release + "-" + hex.EncodeToString(digest.Sum(nil)[:12])
}
