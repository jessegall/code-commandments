package judge

import (
	"crypto/sha256"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	pluginData = "JOURNAL_PLUGIN_DATA"

	rememberedDetectorsFile = "detector-memories.json"
	rememberedWeighingsFile = "csharp-weighings.json"
	readingFile             = "reading"
	rememberedFolderName    = "remembered"
	turnFile                = "judging.lock"
	turnWaitedAtMost        = 2 * time.Minute
)

// Remembered is what one run keeps for the next in its project's own folder, good only for the build that wrote it: what every detector answered for each file's contents, and what the aggregating C# rules found. It lives at the journal of the project root when the journal drives the project, and under the user's cache otherwise: one folder per project that all its worktrees share, so the plugin's runs, queued runs and runs started by hand remember together and claim the same project. Finding it also hands on the hours the project keeps an unused cache, so the run and every bridge it starts prune by them. Judges take turns across the whole machine, so two never run at once and eat its memory: a run waits for the one before it to end, two minutes at most so one stuck judge cannot hold every other, then claims its project while it reads; the system lets go of both the moment a run ends, so a run stopped with ^C or killed blocks nobody.
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
		Memories:  detectors.MemoryFile{Memories: detectors.EveryMemory(), Path: filepath.Join(folder, rememberedDetectorsFile), Build: Build(), Warn: warn},
		Weighings: WeighingsFile{Path: filepath.Join(folder, rememberedWeighingsFile), Build: Build(), Warn: warn},
	}, true
}

func rememberedFolder(project string) string {
	if project == "" {
		return ""
	}

	if main := git.ProjectRoot(project); main != "" {
		project = main
	}

	if space := workspace.At(project, ""); space.IsJournalDriven() {
		return space.StateDir()
	}

	cache, err := bundle.CacheFolder()
	if err != nil {
		return ""
	}
	named := sha256.Sum256([]byte(project))

	return filepath.Join(cache, "code-commandments", rememberedFolderName, hex.EncodeToString(named[:8]))
}

func (r Remembered) ReadAlone() (release func(), alone bool) {
	turn := atomic.HoldInTurn(judgingTurn(), r.waiting, turnWaitedAtMost)

	claimed, alone := atomic.ClaimAt(filepath.Join(filepath.Dir(r.Memories.Path), readingFile)).Take()
	if !alone {
		turn()

		return func() {}, false
	}

	return func() {
		claimed()
		turn()
	}, true
}

func (r Remembered) waiting() {
	if r.Memories.Warn != nil {
		r.Memories.Warn("· another judge is running on this machine, so this one waits its turn, two minutes at most")
	}
}

func judgingTurn() string {
	cache, err := bundle.CacheFolder()
	if err != nil {
		return filepath.Join(os.TempDir(), "code-commandments-"+turnFile)
	}

	return filepath.Join(cache, "code-commandments", turnFile)
}

func (r Remembered) Load() {
	r.Memories.Load()
	r.Weighings.Load()
}

func (r Remembered) Save() {
	r.Memories.Save()
	r.Weighings.Save()
}

var Build = sync.OnceValue(func() string {
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
})
