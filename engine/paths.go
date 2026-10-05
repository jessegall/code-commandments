package engine

import (
	"path/filepath"
	"strings"
)

// Scanned records the folders and files the scan was pointed at, which a path is judged from.
func (c *Codebase) Scanned(roots ...string) {
	c.scanned = roots
}

// Judged is the file's path from the folder its scan was pointed at, that folder's own name included: `src/Cart.php`
// for a scan of `src`, whatever lies above it on the disk. A scan pointed at one file reads it from that file's
// folder, as a scan of the folder would. A rule reads a path this way, so where the project is checked out, and
// whether it was pointed at the file or its folder, never changes what it finds. With no scan recorded, the paths
// its bridge was asked for stand in.
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

// spellings are the ways a root may be written on the disk: as given, and with its symbolic links resolved, as a
// file's path always is.
func spellings(root string) []string {
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

// Judged is the path of the match's file from the folder its scan was pointed at; empty for no match.
func (m Match) Judged() string {
	if m.file == nil {
		return ""
	}

	return m.file.Judged()
}

// InFolderNamed says whether a folder on the path has one of the names.
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
