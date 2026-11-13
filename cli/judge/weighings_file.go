package judge

import "github.com/jessegall/code-commandments/cache"

// WeighingsFile is the file one project keeps what its aggregating C# rules found in between runs of its service, good only for the build that wrote it: the newest few per rule, one per set of C# contents, so worktrees that hold different C# keep theirs side by side.
type WeighingsFile struct {
	Path  string
	Build string
	Warn  func(...string)
}

func (f WeighingsFile) Load() {
	if saved, read := f.stamped().Read(); read {
		absorb(saved)
	}
}

func (f WeighingsFile) Save() {
	weighingsHeld.Lock()
	changed := weighingsChanged
	weighingsChanged = false
	weighingsHeld.Unlock()

	if changed {
		f.stamped().Save(func(saved map[string]weighing) map[string]weighing {
			absorb(saved)
			weighingsHeld.Lock()
			defer weighingsHeld.Unlock()

			return heldWeighings()
		})
	}
}

func (f WeighingsFile) stamped() cache.Stamped[map[string]weighing] {
	return cache.Stamped[map[string]weighing]{Path: f.Path, Build: f.Build, What: "The saved findings of the C# rules that read across files", Warn: f.Warn}
}

// absorb takes in the saved findings the run does not hold a newer one of, keeping the newest per rule.
func absorb(saved map[string]weighing) {
	weighingsHeld.Lock()
	defer weighingsHeld.Unlock()

	for key, kept := range saved {
		if _, newer := weighings[key]; !newer {
			weighings[key] = kept
		}
	}
	newestPerRule(weighings)
}
