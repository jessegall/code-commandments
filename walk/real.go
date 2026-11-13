package walk

import (
	"io/fs"
	"os"
	"path/filepath"
	"sync"
)

// realPaths holds each path the walker resolved, once a process: a run asks the same folders again for every file under
// them, and every rule asks again for every file.
var realPaths sync.Map

// Real is the path with every link resolved, made absolute, or the path as given when it cannot be resolved.
func Real(path string) string {
	resolved, err := Resolved(path)
	if err != nil {
		return path
	}

	return resolved
}

// Resolved is the path with every link resolved, made absolute. A path is resolved from its folder's resolved path, so a
// file the walker meets costs one look at the file itself; a path that is not there yet is asked again next time.
func Resolved(path string) (string, error) {
	absolute, err := filepath.Abs(path)
	if err != nil {
		return "", err
	}
	if held, ok := realPaths.Load(absolute); ok {
		return held.(string), nil
	}
	resolved, err := resolvedFromFolder(absolute)
	if err != nil {
		return "", err
	}
	realPaths.Store(absolute, resolved)

	return resolved, nil
}

func resolvedFromFolder(absolute string) (string, error) {
	folder := filepath.Dir(absolute)
	if folder == absolute {
		return filepath.EvalSymlinks(absolute)
	}
	info, err := os.Lstat(absolute)
	if err != nil {
		return "", err
	}
	if info.Mode()&fs.ModeSymlink != 0 {
		linked, err := filepath.EvalSymlinks(absolute)
		if err != nil {
			return "", err
		}

		return filepath.Abs(linked)
	}
	realFolder, err := Resolved(folder)
	if err != nil {
		return "", err
	}

	return filepath.Join(realFolder, filepath.Base(absolute)), nil
}
