package source

import (
	"path/filepath"
	"strings"
)

// Relative is the path from root, both with their symbolic links resolved where they exist; the path as given
// when it lies outside root. A location, `path:line`, reads the same way.
func Relative(root, path string) string {
	if real, err := filepath.EvalSymlinks(root); err == nil {
		root = real
	}

	file, line, located := strings.Cut(path, ":")
	if real, err := filepath.EvalSymlinks(file); err == nil {
		file = real
	}

	inside, under := strings.CutPrefix(file, strings.TrimRight(root, "/")+"/")
	if !under {
		return path
	}

	if located {
		return inside + ":" + line
	}

	return inside
}
