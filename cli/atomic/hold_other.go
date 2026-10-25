//go:build !unix

package atomic

import (
	"os"
	"path/filepath"
)

// Hold creates the lock file; where there is no advisory lock to take, the caller goes ahead unheld.
func Hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	if file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666); err == nil {
		return func() { file.Close() }
	}

	return func() {}
}
