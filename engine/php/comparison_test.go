package php_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// What a comparison tests against a constant is how a ladder over ONE subject is told from a chain of unrelated
// tests, and PHP answered it nowhere: every rule built on it could be written for Python, C# and Go and not for
// the engine's oldest language. These hold the answer, and hold it to saying nothing where there is no subject.

// phpSource is the codebase of one PHP file holding the source.
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

// TestWhatAComparisonTestsAgainstAConstantIsAnsweredForPHP holds the subject for each side a constant can sit on,
// and for each shape PHP writes a constant as: an enum case, a class constant, a literal and a bare constant name.
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

	// The subject as the file writes it, since a property fetch carries its name on a child of its own and the
	// text is what a reader of a finding sees.
	want := map[int]string{
		16: "$order->state", // the member read, with the enum case on the right
		20: "$count",        // the subject on the right, with the class constant on the left
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

// TestAComparisonOfTwoValuesTestsNoSubject holds the empty answer, which is what keeps a rule about a ladder off
// every equality in the file: two values compared test no subject against anything, and neither do two constants.
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
