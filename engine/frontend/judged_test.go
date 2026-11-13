package frontend_test

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine/frontend"
)

// The bridge writes a file only when it or a folder above it is asked for, each line naming what it read; the rest are
// there to import from and are not written, since the reader keeps every line between runs.
func TestAFileIsWrittenWhenItOrAFolderAboveItIsAskedFor(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"a.ts", "sub/deep/b.ts", "subway/c.ts"} {
		path := filepath.Join(root, name)
		os.MkdirAll(filepath.Dir(path), 0o755)
		if err := os.WriteFile(path, []byte("export const x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	command, err := frontend.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	server, err := bridge.Serve(command)
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	stream, err := server.Ask(bridge.Request{Paths: bridge.Listed(root), Write: []string{filepath.Join(root, "a.ts"), filepath.Join(root, "sub")}})
	if err != nil {
		t.Fatal(err)
	}

	var written []string
	for _, file := range stream.Files {
		written = append(written, file.Path)
		if file.Context {
			t.Errorf("%s is written as context, where only asked files are written", file.Path)
		}
	}
	if want := []string{filepath.Join(root, "a.ts"), filepath.Join(root, "sub/deep/b.ts")}; !slices.Equal(written, want) {
		t.Errorf("the bridge wrote %v, want %v: subway/c.ts lies beside sub, not under it", written, want)
	}
	if !stream.Header.Bridge.Reads {
		t.Error("the header does not say each line names what it read, so the reader keeps none of them")
	}
}
