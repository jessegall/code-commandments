package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

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
		`{"select": "kind:Stmt_Property", "where": [{"unused": true}]}`:  "[Till.php:7]",
		`{"select": "kind:Stmt_Property", "where": [{"unused": false}]}`: "[Till.php:11 Till.php:9]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "parameter", "where": [{"unused": true}, {"hasModifier": "private"}]}`:       "[Till.php:8]",
		`{"select": "type-declaration", "where": [{"members": {"name": "read", "atLeast": 1}}]}`: "[Till.php:5]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "kind:FieldDeclaration", "where": [{"unused": true}]}`:                       "[Till.cs:5]",
		`{"select": "kind:FieldDeclaration", "where": [{"unused": false}]}`:                      "[Till.cs:7 Till.cs:9]",
		`{"select": "type-declaration", "where": [{"members": {"name": "read", "atLeast": 1}}]}`: "[Till.cs:3]",
	} {
		if got := foundAt(t, "csharp", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
