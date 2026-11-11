package detectors

import (
	"encoding/json"
	"errors"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
)

// MemoryFile is the file one project keeps its detectors' memories in between runs, good only for the build that wrote it. Runs save it one at a time, each laying what it learned over what the file already holds, so two runs at once never lose each other's answers.
type MemoryFile struct {
	Memories *Memories
	Path     string
	Build    string
	Warn     func(...string)
}

// savedMemories is the contents of a MemoryFile, stamped with the build whose node ids it holds.
type savedMemories struct {
	Build string
	Kept
}

func (f MemoryFile) Load() {
	if saved, read := f.saved(); read {
		f.Memories.Restore(saved)
	}
}

func (f MemoryFile) saved() (Kept, bool) {
	raw, err := os.ReadFile(f.Path)
	if errors.Is(err, os.ErrNotExist) {
		return Kept{}, false
	}

	if err != nil {
		f.Warn("The detectors' saved memories could not be read, so they start empty: " + err.Error())

		return Kept{}, false
	}

	var saved savedMemories
	if err := json.Unmarshal(raw, &saved); err != nil {
		f.Warn("The detectors' saved memories at " + f.Path + " are unreadable, so they start empty: " + err.Error())

		return Kept{}, false
	}

	if saved.Build != f.Build {
		return Kept{}, false
	}

	return saved.Kept, true
}

func (f MemoryFile) Save() {
	if !f.Memories.Changed() {
		return
	}

	release := atomic.Hold(f.Path + ".lock")
	defer release()

	saved, _ := f.saved()
	kept := f.Memories.Over(saved)

	encoded, err := json.Marshal(savedMemories{f.Build, kept})
	if err != nil {
		f.Warn("The detectors' memories could not be encoded: " + err.Error())

		return
	}

	if err := atomic.Write(f.Path, string(encoded)); err != nil {
		f.Warn("The detectors' memories could not be saved: " + err.Error())
	}
}
