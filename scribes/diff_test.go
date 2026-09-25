package scribes

import (
	"os"
	"path/filepath"
	"testing"
)

func TestTheUnifiedDiffIsPHPsByteForByte(t *testing.T) {
	base := t.TempDir()
	existing := filepath.Join(base, "src", "A.php")
	if err := os.MkdirAll(filepath.Dir(existing), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(existing, []byte("one\ntwo\nthree\nfour\nfive\nsix\nseven\neight\nnine\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	rewrites := Rewrites{}
	rewrites.Set(existing, "one\ntwo\nTHREE\nfour\nfive\nsix\nseven\neight\nnine")
	rewrites.Set(filepath.Join(base, "src", "New.vue"), "<template>\n</template>\n")

	got, err := UnifiedDiff(rewrites, base)
	if err != nil {
		t.Fatal(err)
	}

	// What PHP's UnifiedDiff::of prints for the same rewrites.
	want := "--- a/src/A.php\n+++ b/src/A.php\n@@ -1,9 +1,9 @@\n one\n two\n-three\n+THREE\n four\n five\n six\n seven\n eight\n-nine\n+nine\n\\ No newline at end of file\n--- a/src/New.vue\n+++ b/src/New.vue\n@@ -0,0 +1,2 @@\n+<template>\n+</template>\n"
	if got != want {
		t.Fatalf("got\n%s\nwant\n%s", got, want)
	}
}

func TestAnUnchangedFileDiffsToNothing(t *testing.T) {
	base := t.TempDir()
	path := filepath.Join(base, "A.php")
	if err := os.WriteFile(path, []byte("same\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	rewrites := Rewrites{}
	rewrites.Set(path, "same\n")

	if got, _ := UnifiedDiff(rewrites, base); got != "" {
		t.Fatalf("got %q", got)
	}
}
