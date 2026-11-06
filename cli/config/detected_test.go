package config

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
)

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

func TestAProjectWithNothingToDetectIsJudgedWhole(t *testing.T) {
	detected := DetectRoots(aTreeOf(t, map[string]string{"README.md": "# nothing here\n"}))

	if len(detected) != 1 || detected[0] != "." {
		t.Errorf("a project with nothing to detect is judged as %v, want the project itself", detected)
	}
}

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

func TestTheFoldersLeftOutAreNamedByKindAndNotOneAtATime(t *testing.T) {
	root := aTreeOf(t, map[string]string{
		"composer.json":                           `{"autoload": {"psr-4": {"App\\": "app/"}}}`,
		"app/Order.php":                           "<?php",
		"vendor/a/b/thing.php":                    "<?php",
		"modules/library/web/dist/a.js":           "x",
		"modules/profiles/web/dist/a.js":          "x",
		"modules/library/web/node_modules/p/a.js": "x",
	})

	left := BuiltFolders(root)

	for _, named := range []string{"**/vendor", "**/dist", "**/node_modules"} {
		if !slices.Contains(left, named) {
			t.Errorf("the folders left out are %v; a kind the project keeps is named once, as %s", left, named)
		}
	}

	for _, path := range left {
		if !strings.HasPrefix(path, "**/") {
			t.Errorf("the folders left out name the path %q, which covers that one folder and no other", path)
		}
	}

	for _, absent := range []string{"**/.next", "**/venv", "**/coverage"} {
		if slices.Contains(left, absent) {
			t.Errorf("the folders left out name %s, which this project has nowhere", absent)
		}
	}
}
