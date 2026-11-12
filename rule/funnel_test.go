package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

func funnelFound(t *testing.T, name string, codebase *engine.Codebase) string {
	t.Helper()

	project := custom.Load("..")
	index := slices.IndexFunc(project.Rules, func(read rule.Rule) bool { return read.Name() == name })
	if index < 0 {
		t.Fatalf("this project keeps no rule %s: %v", name, project.Unreadable)
	}
	parsed := project.Rules[index]

	var places []string
	for _, match := range parsed.Find(codebase) {
		places = append(places, fmt.Sprintf("%s:%d", filepath.Base(match.File()), match.Line()))
	}
	sort.Strings(places)

	return fmt.Sprint(places)
}

func TestEveryGoWalkOutsideTheFunnelIsFound(t *testing.T) {
	project := goProject(t, map[string]string{
		"go.mod": "module example.com/shop\n\ngo 1.22\n",
		"walks.go": `package shop

import (
	"io/fs"
	"os"
	"path/filepath"
)

type Tree struct{}

func (Tree) WalkDir(root string) {}

func Sources(root string) {
	filepath.WalkDir(root, func(string, fs.DirEntry, error) error { return nil })
	Tree{}.WalkDir(root)
	os.ReadFile(root)
}

func Entries(root string) {
	os.ReadDir(root)
}
`,
		"walk/walk.go": `package walk

import "os"

func Entries(root string) {
	os.ReadDir(root)
}
`,
	})

	if found := funnelFound(t, "FolderWalkOutsideTheFunnelDetector", project); found != "[walks.go:14 walks.go:20]" {
		t.Errorf("found %s", found)
	}
}

func TestEveryPythonWalkOutsideTheFunnelIsFound(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/tree.py": `def walk(root: str) -> None:
    pass
`,
		"shop/sources.py": `import os
import glob
from os import listdir

from shop.tree import walk


def sources(root: str) -> None:
    os.walk(root)
    walk(root)
    os.path.join(root, "a")


def entries(root: str) -> None:
    listdir(root)
    glob.glob(root + "/*.py")
`,
	})

	if found := funnelFound(t, "PythonFolderWalkOutsideTheFunnelDetector", project); found != "[sources.py:15 sources.py:16 sources.py:9]" {
		t.Errorf("found %s", found)
	}
}

func TestEveryPhpWalkOutsideTheFunnelIsFound(t *testing.T) {
	project := phpProject(t, map[string]string{
		"Sources.php": `<?php

namespace App;

function glob(string $pattern): array
{
    return [];
}

final class Sources
{
    public function listed(string $root): array
    {
        glob($root);
        file_get_contents($root);

        return scandir($root);
    }

    public function walked(string $root): iterable
    {
        new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        return \glob($root . '/*.php');
    }

    public function read(string $root): \SplFileInfo
    {
        return new \SplFileInfo($root);
    }
}
`,
	})

	if found := funnelFound(t, "PhpFolderWalkOutsideTheFunnelDetector", project); found != "[Sources.php:17 Sources.php:24]" {
		t.Errorf("calls: found %s", found)
	}
	if found := funnelFound(t, "PhpFolderIteratorOutsideTheFunnelDetector", project); found != "[Sources.php:22]" {
		t.Errorf("constructions: found %s", found)
	}
}

func TestEveryCSharpWalkOutsideTheFunnelIsFound(t *testing.T) {
	project := csharptest.FromSource(t, map[string]string{"Shop/Sources.cs": `namespace Shop;

public static class Directory
{
    public static string[] GetFiles(string root) => [];
}

public static class Sources
{
    public static void Listed(string root)
    {
        System.IO.Directory.EnumerateFiles(root);
        System.IO.Directory.GetDirectories(root);
        new System.IO.DirectoryInfo(root).EnumerateFileSystemInfos();
        Directory.GetFiles(root);
        System.IO.Directory.Exists(root);
    }
}
`})

	if found := funnelFound(t, "CSharpFolderWalkOutsideTheFunnelDetector", project); found != "[Sources.cs:12 Sources.cs:13 Sources.cs:14]" {
		t.Errorf("found %s", found)
	}
}

func TestAnAllowedPlaceReadsTheToolsOwnFolders(t *testing.T) {
	project := scanned(t, map[string]string{"bridge/roslyn/References.cs": `namespace Bridge;

public static class References
{
    public static void Packs(string root) => System.IO.Directory.EnumerateFiles(root);
}
`})

	if found := funnelFound(t, "CSharpFolderWalkOutsideTheFunnelDetector", project); found != "[]" {
		t.Errorf("found %s in the place the rule allows", found)
	}
}

func scanned(t *testing.T, files map[string]string) *engine.Codebase {
	t.Helper()

	root := t.TempDir()
	for path, contents := range files {
		written := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(written), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(written, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	bridgetest.Roslyn(t, root)

	loaded, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}

	return loaded
}
