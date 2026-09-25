package php_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
)

// Each case's verdict is PHP's Frozen::isFrozenFile's for the same source.
func TestAPHPFileIsFrozenAsThePHPToolReadsIt(t *testing.T) {
	cases := []struct {
		source string
		frozen bool
	}{
		{"<?php\n// @frozen\nclass A {}\n", true},
		{"<?php\n/** @FROZEN */\nclass A {}\n", true},
		{"<?php\n#[Frozen]\nclass A {}\n", true},
		{"<?php\n#[ \n Frozen ]\nclass A {}\n", true},
		{"<?php\n#[\\Frozen]\nclass A {}\n", false},
		{"<?php\n#[Other, Frozen]\nclass A {}\n", false},
		{"<?php\n#[Frozen\\Sub]\nclass A {}\n", false},
		{"<?php\n#[/* x */ Frozen]\nclass A {}\n", false},
		{"<?php\n$s = '@frozen';\n", false},
		{"<?php\n// @frozenish\n", false},
		{"<?php\n// @code-commandments-generated\n", true},
		{"<?php\n$a = 1; # @code-commandments-frozen\n", true},
	}
	for _, c := range cases {
		path := filepath.Join(t.TempDir(), "a.php")
		if err := os.WriteFile(path, []byte(c.source), 0o644); err != nil {
			t.Fatal(err)
		}
		codebase, err := php.Here().Scan(path)
		if err != nil {
			t.Fatal(err)
		}
		if got := codebase.Files()[0].IsFrozen(); got != c.frozen {
			t.Errorf("%q: got %v, want %v", c.source, got, c.frozen)
		}
	}
}
