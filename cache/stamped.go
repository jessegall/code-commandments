package cache

import (
	"encoding/json"
	"errors"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
)

// Stamped is a file one project keeps for its next runs, good only for the build that wrote it. Runs save it one at a
// time, each laying what it holds over what the file holds, so two runs at once never lose each other's work. What
// names the contents in a warning, such as "The detectors' saved memories".
type Stamped[Contents any] struct {
	Path  string
	Build string
	What  string
	Warn  func(...string)
}

// stampedFile is what a Stamped file holds: its contents and the build that wrote them.
type stampedFile[Contents any] struct {
	Build    string
	Contents Contents
}

// Read is what the file holds, and false when it is missing, unreadable or written by another build.
func (f Stamped[Contents]) Read() (Contents, bool) {
	var saved stampedFile[Contents]
	raw, err := os.ReadFile(f.Path)
	if errors.Is(err, os.ErrNotExist) {
		return saved.Contents, false
	}
	if err != nil {
		f.Warn(f.What + " could not be read, so they start empty: " + err.Error())

		return saved.Contents, false
	}
	if err := json.Unmarshal(raw, &saved); err != nil {
		f.Warn(f.What + " at " + f.Path + " are unreadable, so they start empty: " + err.Error())

		return *new(Contents), false
	}
	if saved.Build != f.Build {
		return *new(Contents), false
	}

	return saved.Contents, true
}

// Save writes what over gives, handed what the file holds now (empty when it holds nothing of this build), with no
// other run saving between the read and the write.
func (f Stamped[Contents]) Save(over func(saved Contents) Contents) {
	defer atomic.Hold(f.Path + ".lock")()

	saved, _ := f.Read()
	encoded, err := json.Marshal(stampedFile[Contents]{f.Build, over(saved)})
	if err != nil {
		f.Warn(f.What + " could not be encoded: " + err.Error())

		return
	}
	if err := atomic.Write(f.Path, string(encoded)); err != nil {
		f.Warn(f.What + " could not be saved: " + err.Error())
	}
}
