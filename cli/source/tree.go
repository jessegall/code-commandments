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

	// buildOutputs are where a C# project compiles to, beside its .csproj.
	buildOutputs = []string{"bin", "obj"}
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
// folder, not a virtual environment, not excluded.
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

	return !isBuildOutput(directory) && !excluded.Covers(directory)
}

func isBuildOutput(directory string) bool {
	if !slices.Contains(buildOutputs, filepath.Base(directory)) {
		return false
	}

	projects, _ := filepath.Glob(filepath.Join(filepath.Dir(directory), "*.csproj"))

	return len(projects) > 0
}
