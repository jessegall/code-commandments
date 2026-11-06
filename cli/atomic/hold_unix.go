//go:build unix

package atomic

import (
	"os"
	"path/filepath"
	"syscall"
)

func Hold(path string) func() {
	os.MkdirAll(filepath.Dir(path), 0o777)

	file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0o666)
	if err != nil {
		return func() {}
	}

	syscall.Flock(int(file.Fd()), syscall.LOCK_EX)

	return func() {
		syscall.Flock(int(file.Fd()), syscall.LOCK_UN)
		file.Close()
	}
}
