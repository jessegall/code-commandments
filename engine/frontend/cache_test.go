package frontend

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// written is what the bridge writes, byte for byte, for its arguments.
func written(t *testing.T, bridge Bridge, arguments ...string) string {
	t.Helper()
	command, err := bridge.Command()
	if err != nil {
		t.Skip(err)
	}
	out, err := exec.Command(command[0], append(command[1:], arguments...)...).Output()
	if err != nil {
		t.Fatalf("%v: %v", command, err)
	}

	return string(out)
}

// TestAKeptTreeIsWrittenAsTheBridgeWouldWriteItAfresh holds the bridge's tree cache to the stream an uncached run
// writes: run cold and warm, after a change to a file another imports, after a change to a declaration every file
// sees, and with files judged and read only for context; and a run that changes nothing writes the same again.
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

// TestABridgeWhoseCacheCannotBeWrittenRunsWithNoCache holds the bridge to parsing the files when the cache folder
// is not the run's to write: it is handed no folder, so it never dies on one. A worker sandbox may write its
// worktree and little else, and the bridge used to end after two lines with the files unread.
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
