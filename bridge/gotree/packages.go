package gotree

import (
	"github.com/jessegall/code-commandments/walk"
	"go/build"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

// Package is one Go package as the compiler would compile it: the folder it lives in, the import path it is reached by, the name its files declare, and those files.
type Package struct {
	Folder string
	Path   string
	Name   string
	Files  []string
}

// Module is the module a folder belongs to: where its go.mod is, and the path that file declares.
type Module struct {
	Root string
	Path string
}

func PackagesOf(files []string) []Package {
	modules := map[string]Module{}
	held := map[string]*Package{}
	var order []string

	for _, path := range walk.RealEach(files) {
		folder, name := filepath.Split(path)
		folder = strings.TrimRight(folder, string(filepath.Separator))
		if !builds(folder, name) {
			continue
		}
		clause := packageClause(path)
		if clause == "" {
			continue
		}
		key := folder + "\x00" + clause
		if _, seen := held[key]; !seen {
			held[key] = &Package{Folder: folder, Name: clause, Path: importPath(folder, clause, modules)}
			order = append(order, key)
		}
		held[key].Files = append(held[key].Files, path)
	}

	packages := make([]Package, 0, len(order))
	for _, key := range order {
		one := held[key]
		one.Files = append(one.Files, siblingsOf(*one)...)
		sort.Strings(one.Files)
		packages = append(packages, *one)
	}
	sort.SliceStable(packages, func(a, b int) bool {
		if packages[a].Folder != packages[b].Folder {
			return packages[a].Folder < packages[b].Folder
		}

		return packages[a].Name < packages[b].Name
	})

	return packages
}

func siblingsOf(one Package) []string {
	held := map[string]bool{}
	for _, path := range one.Files {
		held[filepath.Base(path)] = true
	}

	var siblings []string
	for _, path := range walk.Entries(one.Folder) {
		name := filepath.Base(path)
		if held[name] || !strings.HasSuffix(name, ".go") || !builds(one.Folder, name) {
			continue
		}
		if packageClause(path) == one.Name {
			siblings = append(siblings, path)
		}
	}

	return siblings
}

func builds(folder, name string) bool {
	context := build.Default
	context.UseAllFiles = false
	matches, err := context.MatchFile(folder, name)

	return err == nil && matches
}

func packageClause(path string) string {
	parsed, err := parser.ParseFile(token.NewFileSet(), path, nil, parser.PackageClauseOnly)
	if err != nil || parsed == nil || parsed.Name == nil {
		return ""
	}

	return parsed.Name.Name
}

func importPath(folder, clause string, modules map[string]Module) string {
	module, found := moduleOf(folder, modules)
	if !found {
		return ""
	}
	path := module.Path
	if relative, err := filepath.Rel(module.Root, folder); err == nil && relative != "." {
		path += "/" + filepath.ToSlash(relative)
	}
	if strings.HasSuffix(clause, "_test") {
		return path + "_test"
	}

	return path
}

func moduleOf(folder string, modules map[string]Module) (Module, bool) {
	if held, read := modules[folder]; read {
		return held, held.Path != ""
	}

	at := folder
	for {
		if path := modulePath(filepath.Join(at, "go.mod")); path != "" {
			module := Module{Root: at, Path: path}
			modules[folder] = module

			return module, true
		}
		parent := filepath.Dir(at)
		if parent == at {
			modules[folder] = Module{}

			return Module{}, false
		}
		at = parent
	}
}

func modulePath(path string) string {
	text, err := os.ReadFile(path)
	if err != nil {
		return ""
	}
	for _, line := range strings.Split(string(text), "\n") {
		if declared, named := strings.CutPrefix(strings.TrimSpace(line), "module "); named {
			return strings.TrimSpace(declared)
		}
	}

	return ""
}
