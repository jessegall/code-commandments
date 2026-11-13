package engine

import (
	"path/filepath"
	"strings"
	"sync"
)

func (c *Codebase) Scanned(roots ...string) {
	c.scanned = roots
}

func (f *File) Judged() string {
	path := filepath.ToSlash(f.Path)
	best := ""

	roots := f.codebase.scanned
	if len(roots) == 0 {
		roots = f.stream.Header.Roots
	}

	for _, root := range roots {
		for _, spelt := range spellings(root) {
			spelt = strings.TrimSuffix(filepath.ToSlash(spelt), "/")
			if (path == spelt || strings.HasPrefix(path, spelt+"/")) && (best == "" || len(spelt) < len(best)) {
				best = spelt
			}
		}
	}

	if best == "" {
		return path
	}
	if best == path {
		best = filepath.ToSlash(filepath.Dir(best))
	}

	return strings.TrimPrefix(path, strings.TrimSuffix(filepath.ToSlash(filepath.Dir(best)), "/")+"/")
}

// spelt holds each root's spellings once a process: a rule asks a file's judged path per file, and resolving a root's
// links walks every folder above it.
var spelt sync.Map

func spellings(root string) []string {
	held, ok := spelt.Load(root)
	if !ok {
		held, _ = spelt.LoadOrStore(root, spellingsOf(root))
	}

	return held.([]string)
}

func spellingsOf(root string) []string {
	spelt := []string{root}
	if absolute, err := filepath.Abs(root); err == nil {
		spelt = append(spelt, absolute)
	}

	if resolved, err := filepath.EvalSymlinks(root); err == nil {
		if absolute, err := filepath.Abs(resolved); err == nil {
			spelt = append(spelt, absolute)
		}
	}

	return spelt
}

func (m Match) Judged() string {
	if m.file == nil {
		return ""
	}

	return m.file.Judged()
}

func (c *Codebase) Rooted(project string) {
	c.project = project
}

func (f *File) FromProject() string {
	path := filepath.ToSlash(f.Path)
	if f.codebase.project == "" {
		return f.Judged()
	}
	for _, spelt := range spellings(f.codebase.project) {
		if spelt = strings.TrimSuffix(filepath.ToSlash(spelt), "/"); strings.HasPrefix(path, spelt+"/") {
			return strings.TrimPrefix(path, spelt+"/")
		}
	}

	return f.Judged()
}

func (m Match) FromProject() string {
	if m.file == nil {
		return ""
	}

	return m.file.FromProject()
}

func InFolderNamed(file string, names ...string) bool {
	folders := strings.Split(filepath.ToSlash(filepath.Dir(file)), "/")
	for _, folder := range folders {
		for _, name := range names {
			if folder == name {
				return true
			}
		}
	}

	return false
}
