package config

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAPhpConfigWithNoPhpSaysHowToTurnItIntoJSON(t *testing.T) {
	path := filepath.Join(t.TempDir(), "config.php")
	if err := os.WriteFile(path, []byte("<?php\nreturn function ($config): void {};\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	t.Setenv("PATH", t.TempDir())
	if _, err := ReadPHP(path); err == nil || !strings.Contains(err.Error(), "commandments sync") || !strings.Contains(err.Error(), "config.json") {
		t.Errorf("a config.php with no php answers %v", err)
	}
}
