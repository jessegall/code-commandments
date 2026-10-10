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
	// skippedFolders never hold the project's own source.
	skippedFolders = []string{"vendor", "node_modules", "site-packages", "__pycache__"}

	// skippedSuffixes end the name of a folder a package installer made.
	skippedSuffixes = []string{".egg-info"}

	// buildOutputs are the folders a build writes to, by the file beside them that says a build lives there: a C#
	// project's .csproj, a JavaScript package's package.json, and Laravel's artisan, whose public folder holds only
	// what the asset build compiled.
	buildOutputs = map[string][]string{
		"*.csproj":     {"bin", "obj"},
		"package.json": {"dist", "build"},
		"artisan":      {"public"},
	}
)

// virtualEnvironment marks a Python virtual environment's folder.
const virtualEnvironment = "pyvenv.cfg"

// Sources are the files under path in a language the tool reads, skipping what is never source and what
// the project excluded. A path that is a file answers itself when it is one.
func Sources(path string, excluded Excluded) []string {
	return walk(path, excluded, Judges)
}

// FilesIn are the files under path with this extension, skipping the same folders Sources does.
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

// descend collects the wanted files under dir in the order the PHP tool met them (listing.Of), going into
// each folder as it is met, as PHP's recursive directory iterator does; the report's twins and the dashboard
// keep that order.
func descend(dir string, excluded Excluded, wanted func(string) bool, files []string) []string {
	for _, name := range listing.Of(dir) {
		path := dir + "/" + name
		info, err := os.Stat(path)

		switch {
		case err != nil:
		case info.IsDir():
			if descends(path, excluded) {
				files = descend(path, excluded, wanted, files)
			}
		case info.Mode().IsRegular() && wanted(path):
			files = append(files, path)
		}
	}

	return files
}

// Repositories are the git repositories checked out in folders beneath path that a walk of it reaches, each a folder
// holding a .git of its own beside the history path lies in: a workspace's independent checkouts. A worktree a helper
// checked out is none of the project's and is never reached.
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

// Reaches says whether a walk from root would come down to file: no folder between them is skipped.
func Reaches(root, file string, excluded Excluded) bool {
	for directory := filepath.Dir(file); strings.HasPrefix(directory, root+"/"); directory = filepath.Dir(directory) {
		if !descends(directory, excluded) {
			return false
		}
	}

	return true
}

// descends says whether a walk goes into the folder: not a link, not hidden, not a dependency or build
// folder, not a git worktree checked out inside the project, not a virtual environment, not excluded.
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

// IsWorktree says whether the folder is a git worktree checked out inside the project, a helper's copy of it: its
// .git is a file naming a worktree of a repository. A submodule's .git file names a module, and a repository of its
// own has a .git folder; both are the project's code.
func IsWorktree(directory string) bool {
	pointer, err := os.ReadFile(filepath.Join(directory, ".git"))

	return err == nil && strings.Contains(filepath.ToSlash(string(pointer)), "/worktrees/")
}

// InWorktree says whether the file lies in a git worktree checked out below the project's root.
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
