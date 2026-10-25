package gotree_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
)

// TestFilesAreGroupedAsGoCompilesThem holds the grouping to what the compiler would compile: the files of one
// folder are one package, a test file declaring `package shop_test` beside them is a package of its own, and a
// file this platform does not build is no part of either.
//
// The grouping is what the type checker needs, because it checks a package where the tree's unit is a file: a
// file handed to it on its own has no siblings, so every name they declare would read as unresolved.
func TestFilesAreGroupedAsGoCompilesThem(t *testing.T) {
	root := module(t, map[string]string{
		"go.mod":            "module example.com/shop\n",
		"cart/cart.go":      "package shop\n\nfunc Total() int { return 0 }\n",
		"cart/line.go":      "package shop\n\ntype Line struct{}\n",
		"cart/cart_test.go": "package shop_test\n\nfunc TestTotal() {}\n",
		"cart/never.go":     "//go:build ignore\n\npackage shop\n\nfunc Never() {}\n",
	})
	files := []string{
		filepath.Join(root, "cart/cart.go"),
		filepath.Join(root, "cart/line.go"),
		filepath.Join(root, "cart/cart_test.go"),
		filepath.Join(root, "cart/never.go"),
	}

	packages := gotree.PackagesOf(files)

	if len(packages) != 2 {
		t.Fatalf("the files are grouped into %d packages: %v", len(packages), named(packages))
	}

	own, test := packages[0], packages[1]
	if own.Name != "shop" || test.Name != "shop_test" {
		t.Fatalf("the packages are named %q and %q", own.Name, test.Name)
	}
	if len(own.Files) != 2 {
		t.Errorf("the package holds %v, where two files declare it and one is not built on any platform", own.Files)
	}
	for _, held := range own.Files {
		if filepath.Base(held) == "never.go" {
			t.Error("a file no platform builds was grouped into the package")
		}
	}
	if len(test.Files) != 1 {
		t.Errorf("the test package holds %v", test.Files)
	}
	if own.Path != "example.com/shop/cart" {
		t.Errorf("the package is imported by %q", own.Path)
	}
	if test.Path != "example.com/shop/cart_test" {
		t.Errorf("the test package is imported by %q", test.Path)
	}
}

// TestAFolderOutsideAModuleHasNoImportPath holds a folder with no go.mod above it to the honest answer: nothing
// can import it, so its path is empty rather than invented, and a type reached through such an import stays
// unknown.
func TestAFolderOutsideAModuleHasNoImportPath(t *testing.T) {
	root := module(t, map[string]string{"loose/tool.go": "package tool\n\nfunc Run() {}\n"})

	packages := gotree.PackagesOf([]string{filepath.Join(root, "loose/tool.go")})

	if len(packages) != 1 {
		t.Fatalf("the file is grouped into %d packages", len(packages))
	}
	if packages[0].Path != "" {
		t.Errorf("a folder in no module is imported by %q", packages[0].Path)
	}
	if packages[0].Name != "tool" {
		t.Errorf("the package is named %q", packages[0].Name)
	}
}

// TestTheSameFilesAlwaysGroupTheSameWay holds the grouping to one answer whatever order the files arrive in,
// since the symbols a stream numbers follow it.
func TestTheSameFilesAlwaysGroupTheSameWay(t *testing.T) {
	root := module(t, map[string]string{
		"go.mod":         "module example.com/shop\n",
		"a/one.go":       "package a\n",
		"a/two.go":       "package a\n",
		"b/three.go":     "package b\n",
		"b/four_test.go": "package b\n",
	})
	forwards := []string{
		filepath.Join(root, "a/one.go"), filepath.Join(root, "a/two.go"),
		filepath.Join(root, "b/three.go"), filepath.Join(root, "b/four_test.go"),
	}
	backwards := []string{forwards[3], forwards[2], forwards[1], forwards[0]}

	one, other := gotree.PackagesOf(forwards), gotree.PackagesOf(backwards)

	if len(one) != 2 || len(other) != 2 {
		t.Fatalf("the folders group into %d and %d packages", len(one), len(other))
	}
	for at := range one {
		if one[at].Path != other[at].Path || len(one[at].Files) != len(other[at].Files) {
			t.Fatalf("the order the files arrive in changes the grouping: %v against %v", named(one), named(other))
		}
		for file := range one[at].Files {
			if one[at].Files[file] != other[at].Files[file] {
				t.Errorf("package %s holds its files in another order", one[at].Path)
			}
		}
	}
}

// module writes the files under a fresh folder and answers its resolved path.
func module(t *testing.T, files map[string]string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

// named is each package's import path and name, for a failure to read.
func named(packages []gotree.Package) []string {
	var names []string
	for _, held := range packages {
		names = append(names, held.Path+" ("+held.Name+")")
	}

	return names
}

// TestOneFileIsTypedAgainstItsWholePackage holds a request naming one file to the package it belongs to: the
// checker checks a package, so without its siblings every name they declare would read as unresolved, and the
// hooks judge one file at a time. The siblings are read, not reported on.
func TestOneFileIsTypedAgainstItsWholePackage(t *testing.T) {
	root := module(t, map[string]string{
		"go.mod":       "module example.com/shop\n",
		"cart/cart.go": "package shop\n\nfunc Total() int { return rate() }\n",
		"cart/rate.go": "package shop\n\nfunc rate() int { return 2 }\n",
		"cart/note.go": "package shop_test\n\nfunc note() {}\n",
	})

	packages := gotree.PackagesOf([]string{filepath.Join(root, "cart/cart.go")})

	if len(packages) != 1 {
		t.Fatalf("one file is grouped into %d packages", len(packages))
	}
	if len(packages[0].Files) != 2 {
		t.Fatalf("the package holds %v, where its folder holds a sibling of the same package", packages[0].Files)
	}
	for _, held := range packages[0].Files {
		if filepath.Base(held) == "note.go" {
			t.Error("a file of another package in the same folder was taken into this one")
		}
	}
}
