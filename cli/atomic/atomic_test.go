package atomic

import (
	"os"
	"path/filepath"
	"testing"
)

// A write replaces the file whole and keeps its mode; a program is written runnable; no temporary file stays beside it.
func TestAWriteReplacesTheFileWholeAndAProgramIsWrittenRunnable(t *testing.T) {
	folder := t.TempDir()
	kept, program := filepath.Join(folder, "kept.txt"), filepath.Join(folder, "deeper", "bridge")
	if err := os.WriteFile(kept, []byte("old"), 0o600); err != nil {
		t.Fatal(err)
	}

	if err := Write(kept, "new"); err != nil {
		t.Fatal(err)
	}
	if err := WriteExecutable(program, []byte("#!/bin/sh\n")); err != nil {
		t.Fatal(err)
	}

	if contents, _ := os.ReadFile(kept); string(contents) != "new" {
		t.Errorf("the file holds %q, not the new contents", contents)
	}
	if info, _ := os.Stat(kept); info.Mode().Perm() != 0o600 {
		t.Errorf("the replaced file's mode is %v, not the one it had", info.Mode().Perm())
	}
	if info, err := os.Stat(program); err != nil || info.Mode().Perm() != 0o755 {
		t.Errorf("the program was not written runnable: %v", err)
	}
	if entries, _ := os.ReadDir(folder); len(entries) != 2 {
		t.Errorf("the folder holds %d entries, a temporary file stayed behind", len(entries))
	}
}

// A file reached through a link is written where the link points, and the link stays a link.
func TestAWriteThroughALinkKeepsTheLink(t *testing.T) {
	folder := t.TempDir()
	target, link := filepath.Join(folder, "real.txt"), filepath.Join(folder, "link.txt")
	if err := os.WriteFile(target, []byte("old"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink(target, link); err != nil {
		t.Fatal(err)
	}

	if err := Write(link, "new"); err != nil {
		t.Fatal(err)
	}

	if info, _ := os.Lstat(link); info.Mode()&os.ModeSymlink == 0 {
		t.Error("the link was replaced by a file")
	}
	if contents, _ := os.ReadFile(target); string(contents) != "new" {
		t.Errorf("the file the link points at holds %q, not the new contents", contents)
	}
}
