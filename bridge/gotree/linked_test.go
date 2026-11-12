package gotree

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func TestALinkedFolderOrFileIsNeverReadAsGoSource(t *testing.T) {
	outside := t.TempDir()
	write(t, filepath.Join(outside, "secret.go"), "package secret\n")
	write(t, filepath.Join(outside, "lib", "hidden.go"), "package lib\n")

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	write(t, filepath.Join(root, "go.mod"), "module example.com/shop\n\ngo 1.22\n")
	write(t, filepath.Join(root, "order", "order.go"), "package order\n")
	write(t, filepath.Join(root, "_scratch", "draft.go"), "package scratch\n")
	link(t, filepath.Join(outside, "secret.go"), filepath.Join(root, "order", "linked.go"))
	link(t, filepath.Join(outside, "lib"), filepath.Join(root, "lib"))

	files := GoFiles([]string{root})

	if want := []string{filepath.Join(root, "order", "order.go")}; !slices.Equal(files, want) {
		t.Errorf("the Go bridge read %v, want only %v", files, want)
	}

	if named := GoFiles([]string{filepath.Join(root, "order", "linked.go")}); len(named) != 0 {
		t.Errorf("a linked file named on its own was read: %v", named)
	}
}

func write(t *testing.T, path, text string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func link(t *testing.T, target, at string) {
	t.Helper()

	if err := os.Symlink(target, at); err != nil {
		t.Fatal(err)
	}
}
