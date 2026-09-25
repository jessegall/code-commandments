// Package atomic writes a file whole or not at all: a reader running beside the write sees the old file or
// the new one, never half of either.
package atomic

import (
	"os"
	"path/filepath"
)

// Write puts contents at path through a temporary file beside it and a rename, creating the folder when it
// is not there. An existing file keeps its permissions; a new one gets the umask default.
func Write(path, contents string) error {
	folder := filepath.Dir(path)

	if err := os.MkdirAll(folder, 0o777); err != nil {
		return err
	}

	temporary, err := os.CreateTemp(folder, ".cc-")
	if err != nil {
		return err
	}

	defer os.Remove(temporary.Name())

	if _, err := temporary.WriteString(contents); err != nil {
		temporary.Close()

		return err
	}

	if err := temporary.Close(); err != nil {
		return err
	}

	if err := os.Chmod(temporary.Name(), mode(path)); err != nil {
		return err
	}

	return os.Rename(temporary.Name(), path)
}

func mode(path string) os.FileMode {
	if info, err := os.Stat(path); err == nil && info.Mode().IsRegular() {
		return info.Mode().Perm()
	}

	return 0o666 &^ umask()
}
