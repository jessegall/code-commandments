package scan_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine/php"
)

// A PHP file is written when it or a folder above it is asked for, and a file only read beside them is not written: the
// one cache serves what the bridge does not write.
func TestAPHPFileIsWrittenWhenItOrAFolderAboveItIsAskedForAndNotOtherwise(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"a.php", "sub/deep/b.php", "subway/c.php"} {
		path := filepath.Join(root, name)
		os.MkdirAll(filepath.Dir(path), 0o755)
		if err := os.WriteFile(path, []byte("<?php\n\nfinal class X {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	command, err := php.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	server, err := bridge.Serve(command)
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	stream, err := server.Ask(bridge.Request{Paths: bridge.Listed(root), Write: []string{filepath.Join(root, "a.php"), filepath.Join(root, "sub")}})
	if err != nil {
		t.Fatal(err)
	}

	written := map[string]bool{}
	for _, file := range stream.Files {
		written[file.Path] = !file.Context
	}
	want := map[string]bool{filepath.Join(root, "a.php"): true, filepath.Join(root, "sub/deep/b.php"): true, filepath.Join(root, "subway/c.php"): false}
	for path, is := range want {
		if written[path] != is {
			t.Errorf("%s: written is %v; want %v", path, written[path], is)
		}
	}
}
