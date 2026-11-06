package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestARuleKnowsWhatAMethodOwesItsSupertype(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Handles.php": `<?php

namespace App;

interface Handles
{
    public function handle(Order $order): void;
}
`,
		"app/Shipper.php": `<?php

namespace App;

final class Shipper implements Handles
{
    public function handle(Order $order): void {}
}
`,
		"app/Printer.php": `<?php

namespace App;

final class Printer
{
    public function handle(Order $order): void {}
}
`,
	})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"overrides": true}]}`:  "[Shipper.php:7]",
		`{"select": "function", "where": [{"overrides": false}]}`: "[Printer.php:7]",
		`{"select": "function"}`:                                  "[Printer.php:7 Shipper.php:7]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

func TestARuleKnowsWhatTheLanguageItselfCalls(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `class Till:
    def __init__(self) -> None:
        self.cash = 0

    def take(
        self,
        paid: int,
    ) -> int:
        return self.cash + paid
`,
	})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"implicit": true}]}`:   "[till.py:2]",
		`{"select": "function", "where": [{"implicit": false}]}`:  "[till.py:5]",
		`{"select": "parameter", "where": [{"implicit": true}]}`:  "[till.py:2 till.py:6]",
		`{"select": "parameter", "where": [{"implicit": false}]}`: "[till.py:7]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
