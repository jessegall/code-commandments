package hooks

import (
	"os"
	"path/filepath"
	"testing"

)

func TestAHalfWrittenFileDoesNotLoseTheGroupsParse(t *testing.T) {
	folder, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	whole := filepath.Join(folder, "order.py")
	if err := os.WriteFile(whole, []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	half := filepath.Join(folder, "invoice.py")
	if err := os.WriteFile(half, []byte("class Invoice:\n    def total(:\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	streams, err := readTogether([]string{whole, half})
	if err != nil {
		t.Fatalf("an ask carrying one half-written file answered with nothing at all: %v", err)
	}

	read := map[string]bool{}
	for _, stream := range streams {
		for _, file := range stream.Files {
			read[file.Path] = true
		}
	}

	if !read[whole] {
		t.Errorf("the whole file %s was not read, because the file beside it was half written", whole)
	}
}
