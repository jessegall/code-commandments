package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func resolvedAt(t *testing.T, codebase *engine.Codebase, written string) engine.Match {
	t.Helper()
	for _, node := range codebase.Where(func(node engine.Match) bool { return node.Written() == written }).Get() {
		if node.Node().Role == "expression" {
			return node
		}
	}

	t.Fatalf("no expression written %q in the source", written)

	return engine.Match{}
}

func TestAResolvedTypeKeepsTheTypeItWasReadOff(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Money
{
    public function plus(Money $other): Money
    {
        return $other;
    }
}

final class Till
{
    private ?Money $spare = null;

    public function __construct(private Money $held) {}

    public function take(Money $paid): Money
    {
        $sum = $paid->plus($this->held);
        $maybe = $this->spare;
        $anything = $this->whatever();

        return $sum;
    }
}
`)

	for written, want := range map[string]struct {
		name     string
		nullable bool
		origin   string
	}{
		"$paid":        {"Shop\\Money", false, "written"},
		"$sum":         {"Shop\\Money", false, "written"},
		"$this->held":  {"Shop\\Money", false, "written"},
		"$this->spare": {"Shop\\Money", true, "written"},
		"$maybe":       {"Shop\\Money", true, "written"},
	} {
		t.Run(written, func(t *testing.T) {
			resolved := resolvedAt(t, codebase, written).Node().Resolved
			if resolved == nil {
				t.Fatalf("%s carries no resolved type", written)
			}

			if resolved.Name != want.name || resolved.Nullable != want.nullable || resolved.Origin != want.origin {
				t.Errorf("%s resolves to %q, nullable %v, from %q", written, resolved.Name, resolved.Nullable, resolved.Origin)
			}
		})
	}
}

func TestATypeTheEngineWorkedOutItselfSaysSo(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Slip {}

final class Desk
{
    public function make()
    {
        $slip = new Slip();

        return $slip;
    }
}
`)

	resolved := resolvedAt(t, codebase, "new Slip()").Node().Resolved
	if resolved == nil {
		t.Fatal("a construction carries no resolved type")
	}

	if resolved.Name != "Shop\\Slip" || resolved.Origin != "inferred" {
		t.Errorf("a construction resolves to %q, from %q", resolved.Name, resolved.Origin)
	}
}

func TestAValueTheTypeEngineCannotReadCarriesNoType(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Counter
{
    public function count($rows)
    {
        $total = $rows;

        return $total;
    }
}
`)

	for _, written := range []string{"$rows", "$total"} {
		if resolved := resolvedAt(t, codebase, written).Node().Resolved; resolved != nil {
			t.Errorf("%s carries the type %q, and nothing says what it holds", written, resolved.Text)
		}
	}
}
