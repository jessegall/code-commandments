package bridge

import (
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// InWalkOrder puts the stream's files in the order a walk of the paths meets them, each folder's entries in
// the order the folder lists them, as the PHP tool's own walk does. A bridge sorts what it reads so its stream
// is the same on every machine; a report's twins and a scribe's rewrites follow the walk instead.
func InWalkOrder(stream *contract.Stream, paths []string) {
	at := map[string]int{}

	for _, path := range paths {
		walk(path, at)
	}

	slices.SortStableFunc(stream.Files, func(a, b *contract.File) int {
		return rank(at, a.Path) - rank(at, b.Path)
	})
}

func walk(path string, at map[string]int) {
	info, err := os.Stat(path)
	if err != nil {
		return
	}

	if !info.IsDir() {
		if real := resolved(path); real != "" {
			if _, seen := at[real]; !seen {
				at[real] = len(at)
			}
		}

		return
	}

	folder, err := os.Open(path)
	if err != nil {
		return
	}

	names, _ := folder.Readdirnames(-1)
	folder.Close()

	for _, name := range names {
		walk(filepath.Join(path, name), at)
	}
}

// rank is where the walk met the path; a path it never met goes last.
func rank(at map[string]int, path string) int {
	if i, met := at[resolved(path)]; met {
		return i
	}

	return len(at)
}

func resolved(path string) string {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	absolute, err := filepath.Abs(real)
	if err != nil {
		return real
	}

	return absolute
}
