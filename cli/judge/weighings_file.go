package judge

import (
	"encoding/json"
	"errors"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
)

// WeighingsFile is the file one project keeps what its aggregating C# rules found in between runs of its service, good only for the build that wrote it: the newest few per rule, one per set of C# contents, so worktrees that hold different C# keep theirs side by side, and runs save one at a time, each laying what it found over what the file holds.
type WeighingsFile struct {
	Path  string
	Build string
	Warn  func(...string)
}

// savedWeighings is the contents of a WeighingsFile, stamped with the build whose rules made the findings.
type savedWeighings struct {
	Build     string
	Weighings map[string]weighing
}

func (f WeighingsFile) Load() {
	saved, read := f.saved()
	if !read {
		return
	}

	weighingsHeld.Lock()
	defer weighingsHeld.Unlock()

	for key, kept := range saved {
		if _, newer := weighings[key]; !newer {
			weighings[key] = kept
		}
	}
	newestPerRule(weighings)
}

func (f WeighingsFile) saved() (map[string]weighing, bool) {
	raw, err := os.ReadFile(f.Path)
	if errors.Is(err, os.ErrNotExist) {
		return nil, false
	}

	if err != nil {
		f.Warn("The saved findings of the C# rules that read across files could not be read, so they start empty: " + err.Error())

		return nil, false
	}

	var saved savedWeighings
	if err := json.Unmarshal(raw, &saved); err != nil {
		f.Warn("The saved findings of the C# rules that read across files at " + f.Path + " are unreadable, so they start empty: " + err.Error())

		return nil, false
	}

	return saved.Weighings, saved.Build == f.Build
}

func (f WeighingsFile) Save() {
	weighingsHeld.Lock()
	changed := weighingsChanged
	weighingsChanged = false
	weighingsHeld.Unlock()

	if !changed {
		return
	}

	release := atomic.Hold(f.Path + ".lock")
	defer release()

	f.Load()

	weighingsHeld.Lock()
	kept := heldWeighings()
	weighingsHeld.Unlock()

	encoded, err := json.Marshal(savedWeighings{f.Build, kept})
	if err != nil {
		f.Warn("The findings of the C# rules that read across files could not be encoded: " + err.Error())

		return
	}

	if err := atomic.Write(f.Path, string(encoded)); err != nil {
		f.Warn("The findings of the C# rules that read across files could not be saved: " + err.Error())
	}
}
