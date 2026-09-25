//go:build unix

package sync

import (
	"os"
	"path/filepath"
	"syscall"
)

// hold takes the project's sync lock for the run, and answers what releases it. Two syncs at once would
// each delete what the other just published; a lock that cannot be taken is no lock rather than a refusal.
func hold(path string) func() {
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
