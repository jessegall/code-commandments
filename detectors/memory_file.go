package detectors

import (
	"encoding/json"
	"errors"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/engine"
)

// MemoryFile is the file one project keeps its detectors' memories in between runs of its service, good only for the build that wrote it.
type MemoryFile struct {
	Memories *Memories
	Path     string
	Build    string
	Warn     func(...string)
}

// savedMemories is the contents of a MemoryFile, stamped with the build whose node ids it holds.
type savedMemories struct {
	Build     string
	Detectors map[string][]engine.Verdict
}

func (f MemoryFile) Load() {
	raw, err := os.ReadFile(f.Path)
	if errors.Is(err, os.ErrNotExist) {
		return
	}

	if err != nil {
		f.Warn("The detectors' saved memories could not be read, so they start empty: " + err.Error())

		return
	}

	var saved savedMemories
	if err := json.Unmarshal(raw, &saved); err != nil {
		f.Warn("The detectors' saved memories at " + f.Path + " are unreadable, so they start empty: " + err.Error())

		return
	}

	if saved.Build != f.Build {
		return
	}

	f.Memories.Restore(saved.Detectors)
}

func (f MemoryFile) Save() {
	if !f.Memories.Changed() {
		return
	}

	encoded, err := json.Marshal(savedMemories{f.Build, f.Memories.Verdicts()})
	if err != nil {
		f.Warn("The detectors' memories could not be encoded: " + err.Error())

		return
	}

	if err := atomic.Write(f.Path, string(encoded)); err != nil {
		f.Warn("The detectors' memories could not be saved: " + err.Error())
	}
}
