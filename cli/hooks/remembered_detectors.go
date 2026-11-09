package hooks

import (
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	rememberedDetectorsFile = "detector-memories.json"
	rememberedWeighingsFile = "csharp-weighings.json"
	rememberedSavedEvery    = time.Minute
)

func rememberedDetectorsIn(folder string, warn func(...string)) (detectors.MemoryFile, bool) {
	if folder == "" {
		return detectors.MemoryFile{}, false
	}

	return detectors.MemoryFile{
		Memories: detectors.EveryMemory(),
		Path:     filepath.Join(folder, rememberedDetectorsFile),
		Build:    thisBuild(),
		Warn:     warn,
	}, true
}

func rememberedWeighingsIn(folder string, warn func(...string)) judge.WeighingsFile {
	return judge.WeighingsFile{
		Path:  filepath.Join(folder, rememberedWeighingsFile),
		Build: thisBuild(),
		Warn:  warn,
	}
}

func thisBuild() string {
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

func remembersAcrossRestarts(console cli.Console) (saved func()) {
	remembersTreesAcrossRestarts()

	file, keeps := rememberedDetectorsIn(os.Getenv(pluginData), console.Warn)
	if !keeps {
		return func() {}
	}

	file.Load()

	weighings := rememberedWeighingsIn(os.Getenv(pluginData), console.Warn)
	weighings.Load()

	save := func() {
		file.Save()
		weighings.Save()
	}

	go func() {
		for range time.Tick(rememberedSavedEvery) {
			save()
		}
	}()

	go func() {
		<-cli.StopSignals()
		logServiceStopped("by a signal")
		save()
		treeSaves.save(time.Now())
		os.Exit(0)
	}()

	return save
}
