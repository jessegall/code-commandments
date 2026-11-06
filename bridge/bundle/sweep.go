package bundle

import (
	"io/fs"
	"os"
	"path/filepath"
	"time"
)

const keptFor = 14 * 24 * time.Hour

const sweptEvery = 24 * time.Hour

const sweptMark = ".swept"

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
