package scan_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

func TestNoBridgeReadsALinkedFolderOrFileOrWalksAFolderItself(t *testing.T) {
	for name, each := range map[string]struct {
		extension, text string
		command         func(t *testing.T) []string
	}{
		"php":        {"php", "<?php\n\nfinal class X {}\n", func(t *testing.T) []string { return commandOf(t, php.Here().Command) }},
		"typescript": {"ts", "export const x = 1\n", func(t *testing.T) []string { return commandOf(t, frontend.Here().Command) }},
		"python":     {"py", "class X:\n    pass\n", func(t *testing.T) []string { return bridgetest.Mypy(t) }},
	} {
		t.Run(name, func(t *testing.T) {
			outside := t.TempDir()
			writeAt(t, filepath.Join(outside, "secret."+each.extension), each.text)
			writeAt(t, filepath.Join(outside, "lib", "hidden."+each.extension), each.text)

			root, err := filepath.EvalSymlinks(t.TempDir())
			if err != nil {
				t.Fatal(err)
			}
			own := filepath.Join(root, "src", "order."+each.extension)
			writeAt(t, own, each.text)
			linkedFile := filepath.Join(root, "src", "linked."+each.extension)
			linkAt(t, filepath.Join(outside, "secret."+each.extension), linkedFile)
			linkAt(t, filepath.Join(outside, "lib"), filepath.Join(root, "src", "lib"))

			command := each.command(t)

			stream, err := bridge.Once(command, append(bridge.Listed(root), linkedFile)...)
			if err != nil {
				t.Fatal(err)
			}
			for _, file := range stream.Files {
				if file.Path != own {
					t.Errorf("the %s bridge read %s, which is not the one file of the project", name, file.Path)
				}
			}
			if len(stream.Files) != 1 {
				t.Errorf("the %s bridge read %d files, want the one of the project", name, len(stream.Files))
			}

			if _, err := bridge.Once(command, root); err == nil || !strings.Contains(err.Error(), "is a folder") {
				t.Errorf("the %s bridge was handed a folder and did not refuse it: %v", name, err)
			}
		})
	}
}

func commandOf(t *testing.T, command func() ([]string, error)) []string {
	t.Helper()

	held, err := command()
	if err != nil {
		t.Fatal(err)
	}

	return held
}

func writeAt(t *testing.T, path, text string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func linkAt(t *testing.T, target, at string) {
	t.Helper()

	if err := os.Symlink(target, at); err != nil {
		t.Fatal(err)
	}
}
