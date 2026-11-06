//go:build !unix

package atomic

import (
	"os"
	"path/filepath"
)

func Hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	if file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666); err == nil {
		return func() { file.Close() }
	}

	return func() {}
}
