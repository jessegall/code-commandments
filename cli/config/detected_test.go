package config

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

// The source roots a project is judged by, when it declares none, are detected once and written into its config.
// That detection was proved only by the recorded parity cases, which run the built binary, and it decides what
// every later run reads: a root missed here is a tree nobody judges, and a root invented here is scaffolding
// judged as if it were source.

// aTreeOf is a project holding each of the paths, folders as folders and files with their content.
func aTreeOf(t *testing.T, tree map[string]string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, content := range tree {
		path := filepath.Join(root, name)

		if content == "" {
			if err := os.MkdirAll(path, 0o755); err != nil {
				t.Fatal(err)
			}

			continue
		}
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

// TestTheRootsAreReadFromComposersOwnMap holds where a PHP project's roots come from: the PSR-4 map it already
// wrote, so nobody has to say twice where the source is.
func TestTheRootsAreReadFromComposersOwnMap(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		"composer.json": `{"autoload": {"psr-4": {"App\\": "app/", "Lib\\": ["lib/Domain", "missing"]}}}`,
		"app":           "",
		"lib/Domain":    "",
	})

	detected := DetectRoots(root)

	for _, want := range []string{"app", "lib/Domain"} {
		if !slices.Contains(detected, want) {
			t.Errorf("the roots are %v, which does not hold %s", detected, want)
		}
	}
	if slices.Contains(detected, "missing") {
		t.Errorf("the roots are %v, which holds a folder the map names and the tree does not have", detected)
	}
}

// TestScaffoldingIsNoSourceRoot holds the reason this detection exists at all: a run over a whole project judged
// its seeders, its config and its migrations as if they were the code it ships, and the report was unreadable.
func TestScaffoldingIsNoSourceRoot(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		"composer.json":    `{"autoload": {"psr-4": {"App\\": "app/", "Seed\\": "database/seeders", "H\\": ".hidden"}}}`,
		"app":              "",
		"database/seeders": "",
		".hidden":          "",
		"tests":            "",
	})

	detected := DetectRoots(root)

	for _, unwanted := range []string{"database/seeders", ".hidden", "tests"} {
		if slices.Contains(detected, unwanted) {
			t.Errorf("the roots are %v, which holds %s", detected, unwanted)
		}
	}
	if !slices.Contains(detected, "app") {
		t.Errorf("the roots are %v, which does not hold the source", detected)
	}
}

// TestAFrontEndBesideItsOwnManifestIsARoot holds a project that keeps a front end of its own: the folder is found
// by the manifest beside it, and the folders a build writes are not.
func TestAFrontEndBesideItsOwnManifestIsARoot(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		"web/package.json":  `{}`,
		"web/src":           "",
		"dist/package.json": `{}`,
		"dist/src":          "",
	})

	detected := DetectRoots(root)

	if !slices.Contains(detected, "web/src") {
		t.Errorf("the roots are %v, which does not hold the front end", detected)
	}
	if slices.Contains(detected, "dist/src") {
		t.Errorf("the roots are %v, which holds what a build wrote", detected)
	}
}

// TestAProjectWithNothingToDetectIsJudgedWhole holds the fallback, which is the difference between judging
// everything and judging nothing: a project whose tree says nothing about where its source is gets itself as the
// root, so a run still reads it rather than answering clean about a tree it never opened.
func TestAProjectWithNothingToDetectIsJudgedWhole(t *testing.T) {
	detected := DetectRoots(aTreeOf(t, map[string]string{"README.md": "# nothing here\n"}))

	if len(detected) != 1 || detected[0] != "." {
		t.Errorf("a project with nothing to detect is judged as %v, want the project itself", detected)
	}
}

// TestTheDetectedRootsAreWrittenOnceAndReadBack holds the other half: a project that declares none is given the
// detected ones in its own config, so the next run reads them rather than detecting again.
func TestTheDetectedRootsAreWrittenOnceAndReadBack(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		"composer.json": `{"autoload": {"psr-4": {"App\\": "app/"}}}`,
		"app":           "",
	})

	if _, err := DeclaredRoots(root); err != nil {
		t.Fatal(err)
	}

	project, err := Load(root)
	if err != nil {
		t.Fatal(err)
	}
	if !slices.Contains(project.Paths, "app") {
		t.Errorf("the config declares %v, which does not hold the detected root", project.Paths)
	}
}

// TestADeclaredPathIsNeverOverruledByDetection holds the project's own word: once it says where its source is,
// nothing detects over it, however the tree looks.
func TestADeclaredPathIsNeverOverruledByDetection(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["lib"]}`,
		"composer.json":             `{"autoload": {"psr-4": {"App\\": "app/"}}}`,
		"app":                       "",
		"lib":                       "",
	})

	roots, err := DeclaredRoots(root)
	if err != nil {
		t.Fatal(err)
	}

	if len(roots) != 1 || filepath.Base(roots[0]) != "lib" {
		t.Errorf("the run reads %v, and the project said lib", roots)
	}
}
