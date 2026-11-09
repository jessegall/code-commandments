package hooks

import (
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
)

const (
	rememberedDetectorsFile = "detector-memories.json"
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
	file, keeps := rememberedDetectorsIn(os.Getenv(pluginData), console.Warn)
	if !keeps {
		return func() {}
	}

	file.Load()

	go func() {
		for range time.Tick(rememberedSavedEvery) {
			file.Save()
		}
	}()

	go func() {
		<-cli.StopSignals()
		file.Save()
		os.Exit(0)
	}()

	return file.Save
}
