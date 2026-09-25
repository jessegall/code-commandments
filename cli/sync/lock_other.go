//go:build !unix

package sync

import (
	"os"
	"path/filepath"
)

// hold creates the project's sync lock file; where there is no advisory lock, a run goes ahead unheld.
func hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	if file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666); err == nil {
		return func() { file.Close() }
	}

	return func() {}
}
