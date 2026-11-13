package detectors

import "github.com/jessegall/code-commandments/cache"

// MemoryFile is the file one project keeps its detectors' memories in between runs, good only for the build that wrote it.
type MemoryFile struct {
	Memories *Memories
	Path     string
	Build    string
	Warn     func(...string)
}

func (f MemoryFile) Load() {
	if saved, read := f.stamped().Read(); read {
		f.Memories.Restore(saved)
	}
}

func (f MemoryFile) Save() {
	if f.Memories.Changed() {
		f.stamped().Save(f.Memories.Over)
	}
}

func (f MemoryFile) stamped() cache.Stamped[Kept] {
	return cache.Stamped[Kept]{Path: f.Path, Build: f.Build, What: "The detectors' saved memories", Warn: f.Warn}
}
