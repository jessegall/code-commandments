package config

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strings"
)

var (
	// notSource are top folders a PSR-4 map may name that hold no application source.
	notSource = []string{"database", "tests", "test"}

	// built are folders a build or an installer writes, never source.
	built = []string{"vendor", "node_modules", "dist", "build", "coverage", "storage", "bootstrap/cache", "public/build", ".next", ".nuxt", "venv", ".venv", "__pycache__", "site-packages"}

	// conventions are the folders source lives in by convention.
	conventions = []string{"app", "src", "resources/js"}
)

// DetectRoots are the project's source roots: its composer.json PSR-4 folders (less tests and database),
// the conventional app, src and resources/js, and each JS app's src beside its package.json; the project
// itself when none is found.
func DetectRoots(root string) []string {
	found := map[string]bool{}

	for _, dir := range psr4Dirs(root) {
		top, _, _ := strings.Cut(dir, "/")

		if !slices.Contains(notSource, top) && !strings.HasPrefix(top, ".") {
			found[dir] = true
		}
	}

	for _, convention := range conventions {
		if isDir(root + "/" + convention) {
			found[convention] = true
		}
	}

	manifests, _ := filepath.Glob(root + "/*/package.json")

	for _, manifest := range manifests {
		app := filepath.Base(filepath.Dir(manifest))

		if isDir(root+"/"+app+"/src") && !slices.Contains(built, app) {
			found[app+"/src"] = true
		}
	}

	var roots []string

	for dir := range found {
		if isDir(root + "/" + dir) {
			roots = append(roots, dir)
		}
	}

	sort.Strings(roots)

	if len(roots) == 0 {
		return []string{"."}
	}

	return roots
}

// psr4Dirs are the folders composer.json maps namespaces to.
func psr4Dirs(root string) []string {
	raw, err := os.ReadFile(root + "/composer.json")
	if err != nil {
		return nil
	}

	var manifest struct {
		Autoload struct {
			PSR4 map[string]any `json:"psr-4"`
		} `json:"autoload"`
	}

	if json.Unmarshal(raw, &manifest) != nil {
		return nil
	}

	var dirs []string

	for _, paths := range manifest.Autoload.PSR4 {
		for _, dir := range listOf(paths) {
			if dir = strings.Trim(dir, "/"); dir != "" {
				dirs = append(dirs, dir)
			}
		}
	}

	return dirs
}

// listOf is one path or a list of them.
func listOf(value any) []string {
	switch value := value.(type) {
	case string:
		return []string{value}
	case []any:
		var paths []string

		for _, each := range value {
			if path, isPath := each.(string); isPath {
				paths = append(paths, path)
			}
		}

		return paths
	default:
		return nil
	}
}

// Absolute are the roots under the project, `.` being the project itself.
func Absolute(root string, roots []string) []string {
	root = strings.TrimRight(root, "/")
	absolute := make([]string, len(roots))

	for i, dir := range roots {
		absolute[i] = root + "/" + dir

		if dir == "." {
			absolute[i] = root
		}
	}

	return absolute
}

// DeclaredRoots are the roots judge scans with no path given: the config's, or, when it declares none, the
// detected ones, written into the config so the next run reads them.
func DeclaredRoots(root string) ([]string, error) {
	project, err := Load(root)
	if err != nil {
		return nil, err
	}

	if len(project.Paths) > 0 {
		return Absolute(root, project.Paths), nil
	}

	detected := DetectRoots(root)
	scribe := ScribeIn(root)
	scaffolded, err := scribe.Scaffold(detected)

	if err == nil && !scaffolded {
		err = scribe.EnsurePaths(detected)
	}

	return Absolute(root, detected), err
}

func isDir(path string) bool {
	info, err := os.Stat(path)

	return err == nil && info.IsDir()
}

// ToParse are the roots a run over path parses: a path outside the project is its own world; one under a
// declared root parses the declared roots, so cross-file rules see the whole tree; any other adds itself.
func ToParse(project, path string) ([]string, error) {
	target := strings.TrimRight(realOr(path), "/")
	home := strings.TrimRight(realOr(project), "/")

	if target != home && !strings.HasPrefix(target, home+"/") {
		return []string{target}, nil
	}

	roots, err := DeclaredRoots(project)
	if err != nil {
		return nil, err
	}

	for _, root := range roots {
		real := strings.TrimRight(realOr(root), "/")

		if target == real || strings.HasPrefix(target, real+"/") {
			return roots, nil
		}
	}

	return append(roots, target), nil
}

// realOr is the path with its links resolved, or the path itself when it does not exist.
func realOr(path string) string {
	resolved, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	if absolute, err := filepath.Abs(resolved); err == nil {
		return absolute
	}

	return resolved
}
