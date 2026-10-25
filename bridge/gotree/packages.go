package gotree

import (
	"go/build"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

// Package is one Go package as the compiler would compile it: the folder it lives in, the import path it is
// reached by, the name its files declare, and those files.
//
// A package is the unit a type checker works in, where the tree's unit is a file, so the files a run hands the
// bridge have to be grouped before anything can be typed. Two packages can share a folder, since a test file may
// declare `package shop_test` beside `package shop`, and they are separate packages to the compiler.
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

// PackagesOf groups the files into the packages Go would compile them as, in a stable order.
//
// A file the current platform does not build is left out, which is Go's own answer rather than ours: go/build
// reads the `//go:build` line and the name's own suffixes (`_windows.go`, `_amd64.go`), so a file for another
// operating system is not typed against a package it is no part of. A file with no package clause to read, one
// the parser cannot get that far into, is left out too.
func PackagesOf(files []string) []Package {
	modules := map[string]Module{}
	held := map[string]*Package{}
	var order []string

	for _, path := range resolved(files) {
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

// siblingsOf are the package's other files in its own folder, which the run was not handed.
//
// A package is what the checker checks, so a request naming one file is still typed against the whole package it
// belongs to: without its siblings, every name they declare would read as unresolved, and the hooks judge one
// file at a time. The siblings are read, not reported on, which the file line's context says.
func siblingsOf(one Package) []string {
	entries, err := os.ReadDir(one.Folder)
	if err != nil {
		return nil
	}
	held := map[string]bool{}
	for _, path := range one.Files {
		held[filepath.Base(path)] = true
	}

	var siblings []string
	for _, entry := range entries {
		name := entry.Name()
		if entry.IsDir() || held[name] || !strings.HasSuffix(name, ".go") || !builds(one.Folder, name) {
			continue
		}
		path := filepath.Join(one.Folder, name)
		if packageClause(path) == one.Name {
			siblings = append(siblings, path)
		}
	}

	return siblings
}

// builds says whether the current platform builds the file at all, as go/build decides it.
func builds(folder, name string) bool {
	context := build.Default
	context.UseAllFiles = false
	matches, err := context.MatchFile(folder, name)

	return err == nil && matches
}

// packageClause is the name a file's `package` line declares; empty where the parser cannot read one.
func packageClause(path string) string {
	parsed, err := parser.ParseFile(token.NewFileSet(), path, nil, parser.PackageClauseOnly)
	if err != nil || parsed == nil || parsed.Name == nil {
		return ""
	}

	return parsed.Name.Name
}

// importPath is the path a package is imported by: the module path its go.mod declares, plus the folder's place
// under that module. A test package beside its own files is reached by that path with `_test` after it, which is
// how the toolchain names it. A folder in no module has no import path, so nothing can import it and a type
// reached through one is left unknown rather than guessed.
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

// moduleOf is the module the folder lies in, read from the nearest go.mod above it and remembered, since a run
// asks the same folders again and again.
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

// modulePath is the module path a go.mod declares; empty when the file is not there or declares none.
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
