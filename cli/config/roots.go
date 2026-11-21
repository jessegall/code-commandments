package config

import (
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/walk"
)

var (
	notSource = []string{"database", "tests", "test"}

	built = []string{"vendor", "node_modules", "dist", "build", "coverage", "storage", "bootstrap/cache", "public/build", ".next", ".nuxt", "venv", ".venv", "__pycache__", "site-packages"}

	conventions = []string{"app", "src", "resources/js"}
)

func DetectRoots(root string) []string {
	found := map[string]bool{}

	for _, dir := range source.ComposerFolders(root, "autoload") {
		top, _, _ := strings.Cut(dir, "/")

		if !slices.Contains(notSource, top) && !strings.HasPrefix(top, ".") {
			found[dir] = true
		}
	}

	for _, convention := range conventions {
		if walk.IsFolder(root + "/" + convention) {
			found[convention] = true
		}
	}

	manifests, _ := filepath.Glob(root + "/*/package.json")

	for _, manifest := range manifests {
		app := filepath.Base(filepath.Dir(manifest))

		if walk.IsFolder(root+"/"+app+"/src") && !slices.Contains(built, app) {
			found[app+"/src"] = true
		}
	}

	var roots []string

	for dir := range found {
		if walk.IsFolder(root + "/" + dir) {
			roots = append(roots, dir)
		}
	}

	sort.Strings(roots)

	if len(roots) == 0 {
		return []string{"."}
	}

	return roots
}

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

func DeclaredRoots(root string) ([]string, error) {
	project, err := Load(root)
	if err != nil {
		return nil, err
	}

	if len(project.Paths) > 0 {
		return Absolute(root, project.Paths), nil
	}

	detected := DetectRoots(root)
	scribe := EditorIn(root)
	scaffolded, err := scribe.Scaffold(detected)

	if err == nil && !scaffolded {
		err = scribe.EnsurePaths(detected)
	}

	return Absolute(root, detected), err
}

func (c Config) WalkedRoots(root string) ([]string, error) {
	judged, err := c.judgedRoots(root)
	if err != nil {
		return nil, err
	}

	return append(judged, c.testRoots(root, judged)...), nil
}

func (c Config) ReadOnlyRoots(root string) ([]string, error) {
	judged, err := c.judgedRoots(root)
	if err != nil {
		return nil, err
	}

	return c.testRoots(root, judged), nil
}

func (c Config) testRoots(root string, judged []string) []string {
	excluded := c.Exclusions(root)
	var tests []string

	for _, language := range source.Languages {
		for _, folder := range language.TestFolders(root) {
			full := strings.TrimRight(root, "/") + "/" + folder
			read := append(slices.Clone(judged), tests...)

			if walk.IsFolder(full) && !excluded.Covers(full) && !slices.ContainsFunc(read, func(walked string) bool { return isWithin(full, walked) }) {
				tests = append(tests, full)
			}
		}
	}

	return tests
}

func (c Config) judgedRoots(root string) ([]string, error) {
	roots, err := DeclaredRoots(root)
	if err != nil {
		return nil, err
	}

	for _, folder := range c.JudgedDespiteExclusion() {
		full := strings.TrimRight(root, "/") + "/" + folder
		if !slices.ContainsFunc(roots, func(declared string) bool { return isWithin(full, declared) }) {
			roots = append(roots, full)
		}
	}

	return roots, nil
}

func (c Config) Exclusions(root string) source.Excluded {
	return source.Under(root, c.Excluded).Judging(c.JudgedDespiteExclusion())
}

func isWithin(folder, root string) bool {
	root = strings.TrimRight(root, "/")

	return walk.Within(root, folder)
}

func BuiltFolders(root string) []string {
	var found []string

	for kind := range kindsUnder(root) {
		found = append(found, "**/"+kind)
	}

	sort.Strings(found)

	return found
}

func kindsUnder(root string) map[string]bool {
	kinds := map[string]bool{}

	walk.Folders(root, walk.Under(root, built), func(dir string) {
		for _, kind := range built {
			if walk.IsFolder(dir + "/" + kind) {
				kinds[kind] = true
			}
		}
	})

	return kinds
}

func WritesLanguage(root string, language source.Language) bool {
	for _, dir := range DetectRoots(root) {
		if len(source.FilesIn(root+"/"+dir, language, walk.Excluded{})) > 0 {
			return true
		}
	}

	return false
}
