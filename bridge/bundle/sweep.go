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
	mark := filepath.Join(folder, sweptMark)
	if info, err := os.Stat(mark); err == nil && now.Sub(info.ModTime()) < sweptEvery {
		return
	}

	for _, path := range walk.AllFiles(folder) {
		if info, err := os.Lstat(path); err == nil && path != mark && now.Sub(info.ModTime()) > cache.KeptFor() {
			os.Remove(path)
		}
	}

	if os.MkdirAll(folder, 0o755) == nil {
		os.WriteFile(mark, nil, 0o644)
		os.Chtimes(mark, now, now)
	}
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
