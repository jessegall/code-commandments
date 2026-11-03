package rule_test

import (
	"testing"
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

// What a field is READ by differs by language more than the rest of this plan's questions do, and two gaps are
// open rather than hidden: a C# field declaration carries no name of its own (its declarator does) and a C#
// identifier carries no `refers`, so nothing can resolve a bare-name read to it; and a PHP promoted constructor
// parameter is not among its class's members, so `$this->held` resolves to nothing. Both are filed. Python is in
// neither state and never will be: an instance field there IS an assignment in the constructor, with no
// declaration for a rule to select.
