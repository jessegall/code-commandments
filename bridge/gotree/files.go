package gotree

import (
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

var skippedFolders = map[string]bool{
	"vendor": true, "testdata": true, "node_modules": true, "site-packages": true, "__pycache__": true,
}

func GoFiles(paths []string) []string {
	var found []string
	seen := map[string]bool{}
	keep := func(path string) {
		real := resolve(path)
		if seen[real] || !strings.HasSuffix(real, ".go") {
			return
		}
		if _, err := os.ReadFile(real); err != nil {
			return
		}
		seen[real] = true
		found = append(found, real)
	}

	for _, root := range paths {
		info, err := os.Stat(root)
		if err != nil {
			continue
		}
		if !info.IsDir() {
			keep(root)

			continue
		}
		for _, folder := range foldersUnder(root) {
			for _, file := range filesIn(folder) {
				keep(file)
			}
		}
	}

	return found
}

func foldersUnder(root string) []string {
	var folders []string
	filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || !entry.IsDir() {
			return nil
		}
		if path != root && !walks(entry.Name()) {
			return fs.SkipDir
		}
		folders = append(folders, path)

		return nil
	})
	sort.Strings(folders)

	return folders
}

func filesIn(folder string) []string {
	entries, err := os.ReadDir(folder)
	if err != nil {
		return nil
	}
	var files []string
	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		files = append(files, filepath.Join(folder, entry.Name()))
	}

	return files
}

func walks(name string) bool {
	return !strings.HasPrefix(name, ".") && !strings.HasPrefix(name, "_") && !skippedFolders[name]
}

func read(path string) ([]byte, error) {
	return os.ReadFile(path)
}

func resolved(paths []string) []string {
	real := make([]string, 0, len(paths))
	for _, path := range paths {
		real = append(real, resolve(path))
	}

	return real
}

func resolve(path string) string {
	if real, err := filepath.EvalSymlinks(path); err == nil {
		path = real
	}
	if absolute, err := filepath.Abs(path); err == nil {
		return absolute
	}

	return path
}
