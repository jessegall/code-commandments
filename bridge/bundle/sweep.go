package bundle

import (
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"time"
)

const keptFor = 3 * 24 * time.Hour

const sweptEvery = time.Hour

const heldAtMost int64 = 1 << 30

const sweptMark = ".swept"

// cachedTree is one parsed file kept in the cache, with when it was written and how much room it takes.
type cachedTree struct {
	path    string
	written time.Time
	size    int64
}

func sweep(folder string, now time.Time) {
	sweepKeeping(folder, now, heldAtMost)
}

func sweepKeeping(folder string, now time.Time, atMost int64) {
	mark := filepath.Join(folder, sweptMark)
	if info, err := os.Stat(mark); err == nil && now.Sub(info.ModTime()) < sweptEvery {
		return
	}

	var kept []cachedTree
	var held int64

	filepath.WalkDir(folder, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || entry.Name() == sweptMark {
			return nil
		}

		info, err := entry.Info()
		if err != nil {
			return nil
		}

		if now.Sub(info.ModTime()) > keptFor {
			os.Remove(path)

			return nil
		}

		kept = append(kept, cachedTree{path: path, written: info.ModTime(), size: info.Size()})
		held += info.Size()

		return nil
	})

	removeOldestUntilUnder(kept, held, atMost)

	if os.MkdirAll(folder, 0o755) == nil {
		os.WriteFile(mark, nil, 0o644)
		os.Chtimes(mark, now, now)
	}
}

func removeOldestUntilUnder(kept []cachedTree, held, atMost int64) {
	if held <= atMost {
		return
	}

	sort.Slice(kept, func(a, b int) bool { return kept[a].written.Before(kept[b].written) })

	for _, tree := range kept {
		if held <= atMost {
			return
		}

		if os.Remove(tree.path) == nil {
			held -= tree.size
		}
	}
}
