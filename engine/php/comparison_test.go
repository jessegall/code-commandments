package php_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

func phpSource(t *testing.T, source string) *engine.Codebase {
	t.Helper()

	return phpSourceHolding(t, "", source)
}

func phpSourceHolding(t *testing.T, lock, source string) *engine.Codebase {
	t.Helper()
	root := t.TempDir()
	if lock != "" {
		if err := os.WriteFile(filepath.Join(root, "composer.lock"), []byte(lock), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	path := filepath.Join(root, "Orders.php")
	if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}

	codebase, err := php.Here().Scan(path)
	if err != nil {
		t.Fatal(err)
	}

	return codebase
}

func TestAComparisonOfTwoValuesTestsNoSubject(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace App;

class Orders
{
    public function same(int $a, int $b): bool
    {
        if ($a === $b) {
            return true;
        }

        return 1 === 2;
    }
}
`)

	for _, comparison := range codebase.WhereIs(engine.Comparison).Get() {
		if subject := comparison.ComparisonSubject(); subject.Exists() {
			t.Errorf("line %d tests %q as a subject, and it tests none", comparison.Line(), subject.Written())
		}
	}
}
