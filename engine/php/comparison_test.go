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
	path := filepath.Join(t.TempDir(), "Orders.php")
	if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}

	codebase, err := php.Here().Scan(path)
	if err != nil {
		t.Fatal(err)
	}

	return php.In(codebase)
}

func TestWhatAComparisonTestsAgainstAConstantIsAnsweredForPHP(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace App;

enum State: string
{
    case Paid = 'paid';
}

class Orders
{
    const LIMIT = 10;

    public function read(Order $order, int $count, string $word, bool $flag): bool
    {
        if ($order->state === State::Paid) {
            return true;
        }

        if (self::LIMIT === $count) {
            return true;
        }

        if ($word === 'paid') {
            return true;
        }

        return $flag === true;
    }
}
`)

	want := map[int]string{
		16: "$order->state",
		20: "$count",
		24: "$word",
		28: "$flag",
	}

	for _, comparison := range codebase.WhereIs(engine.Comparison).Get() {
		subject := comparison.ComparisonSubject()
		name, said := want[comparison.Line()]
		if !said {
			continue
		}

		if subject.Written() != name {
			t.Errorf("line %d tests %q against a constant, and the subject is %q", comparison.Line(), subject.Written(), name)
		}
	}
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
