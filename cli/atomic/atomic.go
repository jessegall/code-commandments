// Package atomic writes a file whole or not at all: a reader running beside the write sees the old file or the new one, never half of either.
package atomic

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/walk"
)

// Write writes the file whole, keeping the mode of the file it replaces.
func Write(path, contents string) error {
	return written(path, []byte(contents), mode(path))
}

// WriteExecutable writes a program whole, runnable by everyone, readable by everyone and writable by its owner.
func WriteExecutable(path string, contents []byte) error {
	return written(path, contents, 0o755)
}

// written replaces the file a link points at, never the link.
func written(path string, contents []byte, mode os.FileMode) error {
	path = walk.Real(path)
	folder := filepath.Dir(path)

	if err := os.MkdirAll(folder, 0o777); err != nil {
		return err
	}

	temporary, err := os.CreateTemp(folder, ".cc-")
	if err != nil {
		return err
	}

	defer os.Remove(temporary.Name())

	if _, err := temporary.Write(contents); err != nil {
		temporary.Close()

		return err
	}

	if err := temporary.Close(); err != nil {
		return err
	}

	if err := os.Chmod(temporary.Name(), mode); err != nil {
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
