package judge

import (
	"encoding/json"
	"errors"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
)

// WeighingsFile is the file one project keeps what its aggregating C# rules found in between runs of its service, good only for the build that wrote it.
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
	raw, err := os.ReadFile(f.Path)
	if errors.Is(err, os.ErrNotExist) {
		return
	}

	if err != nil {
		f.Warn("The saved findings of the C# rules that read across files could not be read, so they start empty: " + err.Error())

		return
	}

	var saved savedWeighings
	if err := json.Unmarshal(raw, &saved); err != nil {
		f.Warn("The saved findings of the C# rules that read across files at " + f.Path + " are unreadable, so they start empty: " + err.Error())

		return
	}

	if saved.Build != f.Build {
		return
	}

	weighingsHeld.Lock()
	defer weighingsHeld.Unlock()

	for rule, kept := range saved.Weighings {
		weighings[rule] = kept
	}
}

func (f WeighingsFile) Save() {
	kept, changed := weighingsToSave()
	if !changed {
		return
	}

	encoded, err := json.Marshal(savedWeighings{f.Build, kept})
	if err != nil {
		f.Warn("The findings of the C# rules that read across files could not be encoded: " + err.Error())

		return
	}

	if err := atomic.Write(f.Path, string(encoded)); err != nil {
		f.Warn("The findings of the C# rules that read across files could not be saved: " + err.Error())
	}
}

func weighingsToSave() (map[string]weighing, bool) {
	weighingsHeld.Lock()
	defer weighingsHeld.Unlock()

	if !weighingsChanged {
		return nil, false
	}

	weighingsChanged = false

	kept := make(map[string]weighing, len(weighings))
	for rule, one := range weighings {
		kept[rule] = one
	}

	return kept, true
}
