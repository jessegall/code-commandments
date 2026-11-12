package atomic

import (
	"os"
	"path/filepath"
	"strconv"
	"time"
)

// Claim is a sentinel file one run makes for itself and removes when it is done, so a second run sees the first is busy and steps aside rather than waits. A claim older than its expiry counts as gone, so a run that crashed with it in place blocks the others for that long at most.
type Claim struct {
	path   string
	expiry time.Duration
}

func ClaimAt(path string, expiry time.Duration) Claim {
	return Claim{path: path, expiry: expiry}
}

func (c Claim) Take() (release func(), taken bool) {
	if c.made() {
		return func() { os.Remove(c.path) }, true
	}

	if info, err := os.Stat(c.path); err != nil || time.Since(info.ModTime()) < c.expiry {
		return func() {}, false
	}

	os.Remove(c.path)

	if c.made() {
		return func() { os.Remove(c.path) }, true
	}

	return func() {}, false
}

func (c Claim) made() bool {
	os.MkdirAll(filepath.Dir(c.path), 0o777)

	file, err := os.OpenFile(c.path, os.O_CREATE|os.O_EXCL|os.O_WRONLY, 0o666)
	if err != nil {
		return false
	}
	defer file.Close()

	file.WriteString(strconv.Itoa(os.Getpid()) + "\n")

	return true
}
