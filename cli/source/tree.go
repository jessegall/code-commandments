package source

import (
	"io/fs"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/listing"
)

var (
	skippedFolders = []string{"vendor", "dist", "node_modules", "site-packages", "__pycache__", "testdata"}

	skippedSuffixes = []string{".egg-info"}

	buildOutputs = map[string][]string{
		"*.csproj":     {"bin", "obj"},
		"package.json": {"build"},
		"artisan":      {"public"},
	}
)

const virtualEnvironment = "pyvenv.cfg"

func Sources(path string, excluded Excluded) []string {
	return walk(path, excluded, Judges)
}

func FilesIn(path, extension string, excluded Excluded) []string {
	return walk(path, excluded, func(file string) bool {
		return strings.TrimPrefix(filepath.Ext(file), ".") == extension
	})
}

func walk(root string, excluded Excluded, wanted func(string) bool) []string {
	info, err := os.Stat(root)

	switch {
	case err != nil:
		return nil
	case info.Mode().IsRegular():
		if wanted(root) && !excluded.Covers(root) {
			return []string{root}
		}

		return nil
	case !info.IsDir() || excluded.Covers(root):
		return nil
	}

	return descend(root, excluded, wanted, nil)
}

func descend(dir string, excluded Excluded, wanted func(string) bool, files []string) []string {
	for _, name := range listing.Of(dir) {
		path := dir + "/" + name
		info, err := os.Lstat(path)

		switch {
		case err != nil:
		case info.IsDir():
			if descends(path, excluded) {
				files = descend(path, excluded, wanted, files)
			}
		case info.Mode().IsRegular() && wanted(path) && !excluded.Covers(path):
			files = append(files, path)
		}
	}

	return files
}

func Repositories(path string, excluded Excluded) []string {
	var found []string
	var look func(dir string)
	look = func(dir string) {
		for _, name := range listing.Of(dir) {
			child := dir + "/" + name
			if info, err := os.Stat(child); err != nil || !info.IsDir() || !descends(child, excluded) {
				continue
			}
			if _, err := os.Stat(filepath.Join(child, ".git")); err == nil {
				found = append(found, child)
			}
			look(child)
		}
	}
	if info, err := os.Stat(path); err == nil && info.IsDir() {
		look(path)
	}

	return found
}

func Reaches(root, file string, excluded Excluded) bool {
	if !strings.HasPrefix(filepath.ToSlash(file), filepath.ToSlash(root)+"/") || excluded.Covers(file) {
		return false
	}

	for directory := filepath.Dir(file); strings.HasPrefix(directory, root+"/"); directory = filepath.Dir(directory) {
		if !descends(directory, excluded) {
			return false
		}
	}

	return true
}

func descends(directory string, excluded Excluded) bool {
	name := filepath.Base(directory)

	if info, err := os.Lstat(directory); err == nil && info.Mode()&fs.ModeSymlink != 0 {
		return false
	}

	if strings.HasPrefix(name, ".") || slices.Contains(skippedFolders, name) {
		return false
	}

	for _, suffix := range skippedSuffixes {
		if strings.HasSuffix(name, suffix) {
			return false
		}
	}

	if _, err := os.Stat(filepath.Join(directory, virtualEnvironment)); err == nil {
		return false
	}

	return !isBuildOutput(directory) && !IsWorktree(directory) && !excluded.Covers(directory)
}

func IsWorktree(directory string) bool {
	pointer, err := os.ReadFile(filepath.Join(directory, ".git"))

	return err == nil && strings.Contains(filepath.ToSlash(string(pointer)), "/worktrees/")
}

func InTree(root, file string) bool {
	if real, err := filepath.EvalSymlinks(root); err == nil {
		root = real
	}
	if real, err := filepath.EvalSymlinks(file); err == nil {
		file = real
	}

	return strings.HasPrefix(filepath.Clean(file), filepath.Clean(root)+string(filepath.Separator))
}

func InWorktree(root, file string) bool {
	if real, err := filepath.EvalSymlinks(root); err == nil {
		root = real
	}
	if real, err := filepath.EvalSymlinks(file); err == nil {
		file = real
	}
	root = filepath.Clean(root)
	for folder := filepath.Dir(filepath.Clean(file)); folder != root && strings.HasPrefix(folder, root+string(filepath.Separator)); folder = filepath.Dir(folder) {
		if IsWorktree(folder) {
			return true
		}
	}

	return false
}

func isBuildOutput(directory string) bool {
	for marker, folders := range buildOutputs {
		if !slices.Contains(folders, filepath.Base(directory)) {
			continue
		}
		if found, _ := filepath.Glob(filepath.Join(filepath.Dir(directory), marker)); len(found) > 0 {
			return true
		}
	}

	return false
}
