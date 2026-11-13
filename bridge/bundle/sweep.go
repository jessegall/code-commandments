package bundle

import (
	"os"
	"path/filepath"
	"time"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/walk"
)

const sweptEvery = time.Hour

const sweptMark = ".swept"

// Sweep removes what no run used for the hours the cache keeps, at most once an hour.
func Sweep(folder string, now time.Time) {
	if !dueForSweep(folder, now) {
		return
	}
	mark := filepath.Join(folder, sweptMark)
	for _, path := range walk.AllFiles(folder) {
		if info, err := os.Lstat(path); err == nil && path != mark && now.Sub(info.ModTime()) > cache.KeptFor() {
			os.Remove(path)
		}
	}
}

// Used marks a folder the run uses as used now, and once an hour forgets every folder beside it that no run used for
// the hours the cache keeps: a bridge unpacked for an older release, an older release's binary, the shared folder of
// a project no longer judged.
func Used(folder string) {
	now := time.Now()
	os.Chtimes(folder, now, now)
	if parent := filepath.Dir(folder); dueForSweep(parent, now) {
		ForgetUnused(parent, now)
	}
}

// dueForSweep says whether the folder was last swept an hour ago or more, and marks it swept now when it was.
func dueForSweep(folder string, now time.Time) bool {
	mark := filepath.Join(folder, sweptMark)
	if info, err := os.Stat(mark); err == nil && now.Sub(info.ModTime()) < sweptEvery {
		return false
	}
	if os.MkdirAll(folder, 0o755) == nil && os.WriteFile(mark, nil, 0o644) == nil {
		os.Chtimes(mark, now, now)
	}

	return true
}

func ForgetUnused(parent string, now time.Time) {
	entries, err := os.ReadDir(parent)
	if err != nil {
		return
	}

	for _, entry := range entries {
		info, err := entry.Info()
		if err == nil && entry.IsDir() && now.Sub(info.ModTime()) > cache.KeptFor() {
			os.RemoveAll(filepath.Join(parent, entry.Name()))
		}
	}
}
