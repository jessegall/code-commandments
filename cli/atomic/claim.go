package atomic

import (
	"os"
	"path/filepath"
	"syscall"
)

// Claim is a lock one run takes on a file for as long as it works, so a second run sees the first is busy and steps aside rather than waits. The system lets go of it the moment the run ends, however it ends: a run stopped with ^C, killed or crashed blocks nobody, not even for a moment.
type Claim struct {
	path string
}

func ClaimAt(path string) Claim {
	return Claim{path: path}
}

func (c Claim) Take() (release func(), taken bool) {
	os.MkdirAll(filepath.Dir(c.path), 0o777)

	file, err := os.OpenFile(c.path, os.O_CREATE|os.O_RDWR, 0o666)
	if err != nil {
		return func() {}, false
	}

	if err := syscall.Flock(int(file.Fd()), syscall.LOCK_EX|syscall.LOCK_NB); err != nil {
		file.Close()

		return func() {}, false
	}

	return func() {
		syscall.Flock(int(file.Fd()), syscall.LOCK_UN)
		file.Close()
	}, true
}
