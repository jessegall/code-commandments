package gotree

import (
	"github.com/jessegall/code-commandments/walk"
	"os"
	"path/filepath"
	"slices"
	"strings"
)

func GoFiles(paths []string) []string {
	var found []string
	seen := map[string]bool{}

	for _, root := range paths {
		for _, file := range walk.Files(root, walk.Excluded{}, isGoSourceUnder(root)) {
			if real := walk.Real(file); !seen[real] {
				seen[real] = true
				found = append(found, real)
			}
		}
	}

	return found
}

func isGoSourceUnder(root string) func(path string) bool {
	return func(path string) bool {
		inside, _ := filepath.Rel(root, path)

		return strings.HasSuffix(path, ".go") && !slices.ContainsFunc(strings.Split(filepath.ToSlash(inside), "/"), isIgnoredByGo)
	}
}

func isIgnoredByGo(segment string) bool {
	return strings.HasPrefix(segment, "_")
}

func read(path string) ([]byte, error) {
	return os.ReadFile(path)
}

func resolved(paths []string) []string {
	real := make([]string, 0, len(paths))
	for _, path := range paths {
		real = append(real, walk.Real(path))
	}

	return real
}
