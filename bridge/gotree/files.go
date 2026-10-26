package gotree

import (
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

// skippedFolders are the folders a Go tree never judges: a dependency store, a folder the toolchain itself
// ignores, and the caches other bridges leave behind.
var skippedFolders = map[string]bool{
	"vendor": true, "testdata": true, "node_modules": true, "site-packages": true, "__pycache__": true,
}

// GoFiles is every Go file under the paths that is there to be read, resolved and in walk order, each once.
//
// A file the walk met whose bytes cannot be read is left out: a link to a file that is gone, or a file deleted
// under the run. A hidden folder, a dependency store and a folder the Go toolchain itself ignores are not walked.
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

// foldersUnder are the folders worth reading under the root, the root itself among them, in order: every folder
// beneath it that the Go toolchain would compile, which is what walks decides, and nothing inside one it skips.
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

// filesIn are the paths of the files the folder holds directly; a folder it holds is not one, since the walk
// reached that folder itself. A folder that cannot be read holds nothing as far as this is concerned.
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

// walks says whether a folder is walked at all.
func walks(name string) bool {
	return !strings.HasPrefix(name, ".") && !strings.HasPrefix(name, "_") && !skippedFolders[name]
}

// read is the file's bytes.
func read(path string) ([]byte, error) {
	return os.ReadFile(path)
}

// resolved are the paths with their links resolved, as every path a stream names is.
func resolved(paths []string) []string {
	real := make([]string, 0, len(paths))
	for _, path := range paths {
		real = append(real, resolve(path))
	}

	return real
}

// resolve is the path with its links resolved and made absolute, or the path itself when it does not exist.
func resolve(path string) string {
	if real, err := filepath.EvalSymlinks(path); err == nil {
		path = real
	}
	if absolute, err := filepath.Abs(path); err == nil {
		return absolute
	}

	return path
}
