package walk

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

// Reason is why the funnel refuses to step into a folder; the empty reason lets the walk go in.
type Reason string

const (
	Link       Reason = "link"
	Hidden     Reason = "hidden"
	Dependency Reason = "dependency"
	Virtualenv Reason = "virtualenv"
	Build      Reason = "build"
	Worktree   Reason = "worktree"
	Excluding  Reason = "excluded"
)

func Files(root string, excluded Excluded, wanted func(string) bool) []string {
	if isLink(root) && !IsFolder(root) {
		return nil
	}

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

func Folders(root string, excluded Excluded, visit func(dir string)) {
	if info, err := os.Stat(root); err != nil || !info.IsDir() || excluded.Covers(root) {
		return
	}

	var enter func(dir string)
	enter = func(dir string) {
		visit(dir)

		for _, name := range listing.Of(dir) {
			path := dir + "/" + name
			if info, err := os.Lstat(path); err == nil && info.IsDir() && Descends(path, excluded) {
				enter(path)
			}
		}
	}

	enter(root)
}

func descend(dir string, excluded Excluded, wanted func(string) bool, files []string) []string {
	for _, name := range listing.Of(dir) {
		path := dir + "/" + name
		info, err := os.Lstat(path)

		switch {
		case err != nil:
		case info.IsDir():
			if Descends(path, excluded) {
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
			if info, err := os.Lstat(child); err != nil || !info.IsDir() || !Descends(child, excluded) {
				continue
			}
			if IsRepository(child) {
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

	if info, err := os.Lstat(file); err == nil && !info.Mode().IsRegular() {
		return false
	}

	for directory := filepath.Dir(file); strings.HasPrefix(directory, root+"/"); directory = filepath.Dir(directory) {
		if !Descends(directory, excluded) {
			return false
		}
	}

	return true
}

func Descends(directory string, excluded Excluded) bool {
	return Verdict(directory, excluded) == ""
}

func Verdict(directory string, excluded Excluded) Reason {
	name := filepath.Base(directory)

	switch {
	case isLink(directory):
		return Link
	case strings.HasPrefix(name, "."):
		return Hidden
	case isDependency(name):
		return Dependency
	case holds(directory, virtualEnvironment):
		return Virtualenv
	case isBuildOutput(directory):
		return Build
	case IsWorktree(directory):
		return Worktree
	case excluded.Covers(directory):
		return Excluding
	}

	return ""
}

func Entries(dir string) []string {
	if isLink(dir) {
		return nil
	}

	var files []string
	for _, name := range listing.Of(dir) {
		path := dir + "/" + name
		if info, err := os.Lstat(path); err == nil && info.Mode().IsRegular() {
			files = append(files, path)
		}
	}

	return files
}

func Generated(dir, name string) []string {
	if isLink(dir) || !IsFolder(dir) {
		return nil
	}

	var found []string
	for _, entry := range listing.Of(dir) {
		path := dir + "/" + entry
		info, err := os.Lstat(path)

		switch {
		case err != nil:
		case info.IsDir() && !strings.HasPrefix(entry, "."):
			found = append(found, Generated(path, name)...)
		case info.Mode().IsRegular() && entry == name:
			found = append(found, path)
		}
	}

	return found
}

func IsFolder(path string) bool {
	info, err := os.Stat(path)

	return err == nil && info.IsDir()
}

func IsFile(path string) bool {
	info, err := os.Lstat(path)

	return err == nil && info.Mode().IsRegular()
}

func isLink(path string) bool {
	info, err := os.Lstat(path)

	return err == nil && info.Mode()&fs.ModeSymlink != 0
}

func isDependency(name string) bool {
	return slices.Contains(skippedFolders, name) || slices.ContainsFunc(skippedSuffixes, func(suffix string) bool {
		return strings.HasSuffix(name, suffix)
	})
}

func holds(directory, entry string) bool {
	_, err := os.Lstat(filepath.Join(directory, entry))

	return err == nil
}

func IsRepository(directory string) bool {
	return holds(directory, ".git")
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
