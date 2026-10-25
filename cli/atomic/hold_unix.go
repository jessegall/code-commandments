//go:build unix

package atomic

import (
	"os"
	"path/filepath"
	"syscall"
)

// Hold takes an advisory lock on the path for the caller, and answers what releases it. It is how two runs of this
// tool over one project stay out of each other's way where one would otherwise undo what the other just wrote. A
// lock that cannot be taken is no lock rather than a refusal: the work is worth more than the exclusion.
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
