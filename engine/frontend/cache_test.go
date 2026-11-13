package frontend

import (
	"github.com/jessegall/code-commandments/bridge"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func written(t *testing.T, held Bridge, arguments ...string) string {
	t.Helper()
	command, err := held.Command()
	if err != nil {
		t.Skip(err)
	}
	out, err := exec.Command(command[0], append(command[1:], bridge.ListedArguments(arguments...)...)...).Output()
	if err != nil {
		t.Fatalf("%v: %v", command, err)
	}

	return string(out)
}

func TestAKeptTreeIsWrittenAsTheBridgeWouldWriteItAfresh(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	put := func(path, source string) {
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	put("price.ts", "export const price = 12\n")
	put("total.ts", "import { price } from './price'\nexport const total = price * 2\n")
	put("globals.d.ts", "declare const CURRENCY: number\n")
	put("Label.vue", "<script setup lang=\"ts\">\nimport { total } from './total'\nconst shown = total + CURRENCY\n</script>\n\n<template>\n    <span>{{ shown }}</span>\n</template>\n")
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()
	same := func(why string, arguments ...string) {
		t.Helper()
		want := written(t, fresh, arguments...)
		for _, run := range []string{"first", "again"} {
			if got := written(t, kept, arguments...); got != want {
				t.Errorf("%s, the %s cached run writes another stream than an uncached one", why, run)
			}
		}
	}

	same("as written", root)
	if trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree")); len(trees) != 4 {
		t.Fatalf("the cache keeps %d trees for 4 files", len(trees))
	}
	put("price.ts", "export const price = 'twelve'\n")
	same("after a change to a file total.ts imports", root)
	put("globals.d.ts", "declare const CURRENCY: string\n")
	same("after a change to a declaration every file sees", root)
	same("with one file judged and the others context", "--write="+filepath.Join(root, "total.ts"), root)
	same("judged whole again", root)
}

func TestABridgeWhoseCacheCannotBeWrittenRunsWithNoCache(t *testing.T) {
	cache := t.TempDir()
	t.Setenv("XDG_CACHE_HOME", cache)
	trees := filepath.Join(cache, "code-commandments", "trees")
	if err := os.MkdirAll(trees, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(trees, "frontend"), nil, 0o644); err != nil {
		t.Fatal(err)
	}

	bridge := Here().Cached()
	if bridge.Trees != "" {
		t.Errorf("the bridge keeps its trees in %s, which it cannot write", bridge.Trees)
	}

	command, err := bridge.Command()
	if err != nil {
		t.Skip(err)
	}
	for _, argument := range command {
		if strings.HasPrefix(argument, "--cache=") {
			t.Errorf("the bridge is told %s although nothing may be kept there", argument)
		}
	}
}

func TestAKeptTreeTheBridgeUsesAgainIsRenewedAndOneItDoesNotUseIsNot(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{"price.ts": "export const price = 12\n", "tax.ts": "export const tax = 2\n"} {
		if err := os.WriteFile(filepath.Join(root, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	kept := Here()
	kept.Trees = t.TempDir()
	written(t, kept, root)

	old := time.Now().Add(-13 * time.Hour)
	trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree"))
	for _, tree := range trees {
		if err := os.Chtimes(tree, old, old); err != nil {
			t.Fatal(err)
		}
	}

	written(t, kept, root)

	for _, tree := range trees {
		if info, err := os.Stat(tree); err != nil || time.Since(info.ModTime()) > time.Hour {
			t.Errorf("%s was used again and not renewed: %v", filepath.Base(tree), err)
		}
	}
}

func TestTwoCheckoutsHoldingTheSameFilesShareTheirKeptTrees(t *testing.T) {
	parent, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	checkouts := []string{filepath.Join(parent, "main"), filepath.Join(parent, "worktree")}
	for _, checkout := range checkouts {
		if err := os.MkdirAll(checkout, 0o755); err != nil {
			t.Fatal(err)
		}
		for name, source := range map[string]string{"price.ts": "export const price = 12\n", "total.ts": "import { price } from './price'\nexport const total = price * 2\n"} {
			if err := os.WriteFile(filepath.Join(checkout, name), []byte(source), 0o644); err != nil {
				t.Fatal(err)
			}
		}
	}
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()

	written(t, kept, checkouts[0])
	got := written(t, kept, checkouts[1])

	if trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree")); len(trees) != 2 {
		t.Errorf("two checkouts holding two files keep %d trees, where they share two", len(trees))
	}
	if want := written(t, fresh, checkouts[1]); got != want {
		t.Errorf("the second checkout read the shared trees as another stream than its own:\n%s\nwant:\n%s", got, want)
	}
}

func TestTheSameFileBesideOtherImportsNamesItsOwnImports(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, folder := range []string{"first", "second"} {
		if err := os.MkdirAll(filepath.Join(root, folder), 0o755); err != nil {
			t.Fatal(err)
		}
		for name, source := range map[string]string{"price.ts": "export const price = 12\n", "total.ts": "import { price } from './price'\nexport const total = price * 2\n"} {
			if err := os.WriteFile(filepath.Join(root, folder, name), []byte(source), 0o644); err != nil {
				t.Fatal(err)
			}
		}
	}
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()
	want := written(t, fresh, root)

	for _, run := range []string{"first", "again"} {
		if got := written(t, kept, root); got != want {
			t.Errorf("the %s cached run names another file's import than an uncached run:\n%s\nwant:\n%s", run, got, want)
		}
	}
}

func TestAVueFileMetBeforeIsNotParsedToFindItsScripts(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Label.vue"), []byte("<script setup lang=\"ts\">\nconst shown: number = 1\n</script>\n\n<template>\n    <span>{{ shown }}</span>\n</template>\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	kept := Here()
	kept.Trees = t.TempDir()
	first := written(t, kept, root)

	planted, _ := filepath.Glob(filepath.Join(kept.Trees, "memo", "scripts-*"))
	if len(planted) != 1 {
		t.Fatalf("the first run kept %d script ranges for one Vue file", len(planted))
	}
	if err := os.WriteFile(planted[0], []byte("[]"), 0o644); err != nil {
		t.Fatal(err)
	}
	trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree"))
	for _, tree := range trees {
		os.Remove(tree)
	}

	if again := written(t, kept, root); again == first {
		t.Error("a Vue file whose script ranges were kept was parsed again for them: the planted ranges changed nothing")
	}
}

func TestComponentsThatImportEachOtherKeepATreeEach(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	put := func(path, source string) {
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	put("Node.vue", "<script setup>\nimport Card from './Card.vue'\n</script>\n\n<template>\n    <section><Card /></section>\n</template>\n")
	put("Card.vue", "<script setup>\nimport Node from './Node.vue'\n</script>\n\n<template>\n    <article><h2>card</h2><Node /></article>\n</template>\n")
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()

	want := written(t, fresh, root)
	for _, run := range []string{"first", "again"} {
		if got := written(t, kept, root); got != want {
			t.Errorf("the %s cached run serves one component's tree for the other, which imports it back", run)
		}
	}
	if trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree")); len(trees) != 2 {
		t.Errorf("two components in one import cycle keep %d trees, want one each", len(trees))
	}
}
