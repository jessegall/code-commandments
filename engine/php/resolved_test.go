package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// What an expression's type IS, rather than what class it names, is the fact every type question of a rule reads:
// whether the value admits absence, what shape its type has, and whether a human wrote that type, a docblock
// claimed it or the engine worked it out. PHP filled a name and called the answer its own inference, so each of
// those questions would have answered the same for every PHP value in existence. These hold the type the engine
// read it off instead, and hold absence to staying absence.

// resolvedAt is the first expression written exactly so. The role is asked for because a call wraps each argument
// in a node of its own that spans the same text, and that wrapper is no expression.
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

// TestAResolvedTypeKeepsTheTypeItWasReadOff holds the three the rules ask about most: a variable holding a class, a
// method's return, and a promoted constructor property — each with the origin of the type the engine read.
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
		// The parameter's own written type, which says the value is there.
		"$paid": {"Shop\\Money", false, "written"},
		// A local holding what a method answered: the return type the method declares.
		"$sum": {"Shop\\Money", false, "written"},
		// The promoted property, read through $this.
		"$this->held": {"Shop\\Money", false, "written"},
		// A nullable property: the absence is part of the type, and was lost before this.
		"$this->spare": {"Shop\\Money", true, "written"},
		// The local the nullable property was assigned to carries the same type, absence and all.
		"$maybe": {"Shop\\Money", true, "written"},
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

// TestATypeTheEngineWorkedOutItselfSaysSo holds the other origin: a construction names its class with nobody
// writing a type anywhere, so the engine says the type is its own inference rather than borrowing the word
// `written`.
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

// TestAValueTheTypeEngineCannotReadCarriesNoType holds absence to being absence: a wrong type is worse than no
// type, so a value nothing says anything about must carry none at all rather than a guess or an empty type object.
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
