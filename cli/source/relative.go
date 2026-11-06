package source

import (
	"path/filepath"
	"strings"
)

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
