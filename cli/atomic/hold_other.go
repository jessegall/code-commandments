//go:build !unix

package atomic

import (
	"os"
	"path/filepath"
	"time"
)

func Hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	if file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666); err == nil {
		return func() { file.Close() }
	}

	return func() {}
}

func HoldInTurn(path string, waiting func(), patience time.Duration) func() {
	return Hold(path)
}

// shortcut: no system lock here, so a claim is always taken, as Hold always holds; give it LockFileEx when Windows ships.
func tryLock(*os.File) bool {
	return true
}

func unlock(*os.File) {}
