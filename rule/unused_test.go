package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// A field nothing reads is a collaborator the class was given and never used, and the `unused` check could not
// answer it: a field refers to no symbol in most languages, since it is read as `$this->held` rather than by a
// name the resolver binds. What reaches it is resolved through the type its receiver holds, which is the same walk
// the `declaration` target makes, indexed once for the whole codebase.

// TestARuleFindsAFieldNothingReads holds the reading against the two look-alikes that matter: a field read only
// inside a closure, which a walk over a method's own statements misses, and a field read in another method.
func TestARuleFindsAFieldNothingReads(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Printer.php": `<?php

namespace App;

final class Printer
{
    public function send(string $slip): void {}
}
`,
		"app/Till.php": `<?php

namespace App;

final class Till
{
    private Printer $unread;

    private Printer $read;

    private Printer $readInAClosure;

    public function __construct(Printer $printer)
    {
        $this->unread = $printer;
        $this->read = $printer;
        $this->readInAClosure = $printer;
    }

    public function close(string $slip): void
    {
        $this->read->send($slip);
    }

    public function later(string $slip): callable
    {
        return function () use ($slip): void {
            $this->readInAClosure->send($slip);
        };
    }
}
`,
	})

	for query, want := range map[string]string{
		// The field nothing reads, and only that one: the writes in the constructor are no reads.
		`{"select": "kind:Stmt_Property", "where": [{"unused": true}]}`: "[Till.php:7]",
		// Read in a method and read inside a closure are both read, and the closure is where a walk over a
		// method's own statements would stop.
		`{"select": "kind:Stmt_Property", "where": [{"unused": false}]}`: "[Till.php:11 Till.php:9]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestAPromotedParameterIsTheFieldItDeclares holds the commonest field shape in modern PHP: a promoted
// constructor parameter declares a field, so a read of `$this->held` resolves to it and a rule asking what the
// class declares sees it. Before this the class claimed not to declare it at all.
func TestAPromotedParameterIsTheFieldItDeclares(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Printer.php": `<?php

namespace App;

final class Printer
{
    public function send(string $slip): void {}
}
`,
		"app/Till.php": `<?php

namespace App;

final class Till
{
    public function __construct(
        private Printer $unread,
        private Printer $read,
    ) {}

    public function close(string $slip): void
    {
        $this->read->send($slip);
    }
}
`,
	})

	for query, want := range map[string]string{
		// The promoted parameter nothing reads, and only that one.
		`{"select": "parameter", "where": [{"unused": true}, {"hasModifier": "private"}]}`: "[Till.php:8]",
		// Both are members of the class now, which is what a rule about what a type declares asks.
		`{"select": "type-declaration", "where": [{"members": {"name": "read", "atLeast": 1}}]}`: "[Till.php:5]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleFindsACSharpFieldNothingReads holds the C# shape of the same question, which reaches its field by a
// different road: a field there is read by its BARE name, `till.Send(slip)` rather than `this.till.Send(slip)`, so
// there is no receiver whose type says which member the read reaches. The resolver's own binding answers it
// instead — the bridge writes `refers` on the name and the field's own symbol on the declaration that declares it
// — and the field read through `this` is held beside it, since that one resolves both ways.
//
// Python is in neither state and never will be: an instance field there IS an assignment in the constructor, with
// no declaration for a rule to select.
func TestARuleFindsACSharpFieldNothingReads(t *testing.T) {
	project := csharptest.FromSource(t, map[string]string{
		"Shop/Printer.cs": `namespace Shop;

public sealed class Printer
{
    public void Send(string slip) {}
}
`,
		"Shop/Till.cs": `namespace Shop;

public sealed class Till
{
    private readonly Printer unread;

    private readonly Printer read;

    private readonly Printer readThroughThis;

    public Till(Printer printer)
    {
        unread = printer;
        read = printer;
        readThroughThis = printer;
    }

    public void Close(string slip)
    {
        read.Send(slip);
        this.readThroughThis.Send(slip);
    }
}
`,
	})

	for query, want := range map[string]string{
		// The field nothing reads, and only that one: the writes in the constructor are no reads.
		`{"select": "kind:FieldDeclaration", "where": [{"unused": true}]}`: "[Till.cs:5]",
		// Read by its bare name and read through `this` are both read.
		`{"select": "kind:FieldDeclaration", "where": [{"unused": false}]}`: "[Till.cs:7 Till.cs:9]",
		// And the class declares each of them by name, which it could not say before: a C# field declaration
		// carries no name of its own, so a rule asking what a type declares found none of its fields.
		`{"select": "type-declaration", "where": [{"members": {"name": "read", "atLeast": 1}}]}`: "[Till.cs:3]",
	} {
		if got := foundAt(t, "csharp", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
