package judge

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	pluginData = "JOURNAL_PLUGIN_DATA"

	rememberedDetectorsFile = "detector-memories.json"
	rememberedWeighingsFile = "csharp-weighings.json"
	readingFile             = "reading"

	readingClaimedFor = 5 * time.Minute
)

// Remembered is what one run keeps for the next in its project's own folder, good only for the build that wrote it: what every detector answered for each file's contents, and what the aggregating C# rules found. It lives in the journal plugin's folder when there is one, and under the user's cache otherwise, so a plain judge run remembers too. Finding it also hands on the hours the project keeps an unused cache, so the run and every bridge it starts prune by them. Only one run reads a project at a time: a run claims the project while it reads, and a second run that finds it claimed stops at once rather than waiting; a claim left by a run that crashed counts as gone after five minutes.
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
	if folder := os.Getenv(pluginData); folder != "" {
		return folder
	}

	cache, err := bundle.CacheFolder()
	if err != nil || project == "" {
		return ""
	}

	named := sha256.Sum256([]byte(project))

	return filepath.Join(cache, "code-commandments", "remembered", hex.EncodeToString(named[:8]))
}

func (r Remembered) ReadAlone() (release func(), alone bool) {
	return atomic.ClaimAt(filepath.Join(filepath.Dir(r.Memories.Path), readingFile), readingClaimedFor).Take()
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

	info, err := os.Stat(path)
	if err != nil {
		return bridge.Release
	}

	return fmt.Sprintf("%s-%d-%d", bridge.Release, info.Size(), info.ModTime().UnixNano())
}
