package scan_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine/php"
)

func TestAPHPFileIsJudgedWhenItOrAFolderAboveItIsWrittenAndContextOtherwise(t *testing.T) {
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

	context := map[string]bool{}
	for _, file := range stream.Files {
		context[file.Path] = file.Context
	}
	want := map[string]bool{filepath.Join(root, "a.php"): false, filepath.Join(root, "sub/deep/b.php"): false, filepath.Join(root, "subway/c.php"): true}
	for path, is := range want {
		if was, held := context[path]; !held || was != is {
			t.Errorf("%s: context is %v, held %v; want %v", path, was, held, is)
		}
	}
}
