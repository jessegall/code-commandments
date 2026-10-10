package bundle

import (
	"io/fs"
	"os"
	"path/filepath"
	"time"
)

// keptFor is how long a kept tree lives without being written again: a file judged on any day since is rewritten
// whenever it changes, and one gone from the project, a test's temporary folder or a removed worktree, is let go.
const keptFor = 14 * 24 * time.Hour

// sweptEvery is how often the trees folder is swept.
const sweptEvery = 24 * time.Hour

// sweptMark names the file whose time says when the folder was last swept.
const sweptMark = ".swept"

// sweep removes every kept tree under folder that has not been written for keptFor, at most once every sweptEvery:
// a tree is kept under its file's path, so without it every path ever judged would keep its tree for good.
func sweep(folder string, now time.Time) {
	mark := filepath.Join(folder, sweptMark)
	if info, err := os.Stat(mark); err == nil && now.Sub(info.ModTime()) < sweptEvery {
		return
	}
	filepath.WalkDir(folder, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || entry.Name() == sweptMark {
			return nil
		}
		if info, err := entry.Info(); err == nil && now.Sub(info.ModTime()) > keptFor {
			os.Remove(path)
		}

		return nil
	})
	if os.MkdirAll(folder, 0o755) == nil {
		os.WriteFile(mark, nil, 0o644)
		os.Chtimes(mark, now, now)
	}
}
