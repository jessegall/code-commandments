package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// Two exemptions every calibrated rule needs, which the shipped detectors each reject by hand in their own
// language: a signature is not the overriding method's to change, and nothing the project writes calls a
// constructor or a dunder by that name. Without them a project's own rule about a signature cannot be written at
// all, and with a name check in their place it is wrong — a method merely SHARING a name with another class's
// method is nobody's to answer for.

// TestARuleKnowsWhatAMethodOwesItsSupertype holds the reading against the look-alike a name check cannot tell
// apart: the same method name on a class that owes it to nobody.
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
		// Every function the scan holds, so what the two answers above add up to is visible: an interface's own
		// declaration has no body and is no function of the scan.
		`{"select": "function"}`: "[Printer.php:7 Shipper.php:7]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleKnowsWhatTheLanguageItselfCalls holds the other exemption in the language with the most of it: a dunder
// and the parameter the language binds, against the method the project calls itself.
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
		// The constructor the language calls, against the method the project calls itself.
		`{"select": "function", "where": [{"implicit": true}]}`:  "[till.py:2]",
		`{"select": "function", "where": [{"implicit": false}]}`: "[till.py:5]",
		// The parameter the language binds, against the one a caller fills.
		`{"select": "parameter", "where": [{"implicit": true}]}`:  "[till.py:2 till.py:6]",
		`{"select": "parameter", "where": [{"implicit": false}]}`: "[till.py:7]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
