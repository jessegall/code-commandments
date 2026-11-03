package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// A literal's VALUE is written by every bridge and was read by nothing, so a rule about a flag handed to a call had
// to match text: `{"is": "literal"}` cannot tell a bare `true` from a label, and `{"text": "true"}` matches a
// string that merely says so. These hold the value with the kind as part of the question, which is what makes the
// two look-alikes answer differently.

// TestARuleAsksALiteralForItsValue holds a boolean argument against the three things a text match confuses it
// with: the string "true", the number 1, and a name that reads as one.
func TestARuleAsksALiteralForItsValue(t *testing.T) {
	codebase := phpCodebase(t, "Till.php", `<?php

namespace App;

final class Till
{
    public function close(Printer $printer): void
    {
        $printer->send(true);
        $printer->send('true');
        $printer->send(1);
        $printer->send(TRUE);
        $printer->send(null);
        $printer->send(0.0);
    }
}
`)

	for query, want := range map[string]string{
		// The bare boolean, written either way, and nothing else.
		`{"select": "call", "where": [{"argument": {"at": 0, "value": true}}]}`:    "[9 12]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": "true"}}]}`:  "[10]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": 1}}]}`:       "[11]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": null}}]}`:    "[13]",
		// A number is read as a number, so zero is zero however it is spelled.
		`{"select": "call", "where": [{"argument": {"at": 0, "value": 0}}]}`: "[14]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestTheValueReadsTheSameInASecondLanguage holds it where the booleans are spelled differently and the bridge is
// another one's, since what a rule asks for is the decoded value and not the token.
func TestTheValueReadsTheSameInASecondLanguage(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `def send(flag: object) -> None:
    pass


def close() -> None:
    send(True)
    send("True")
    send(1)
    send(None)
`,
	})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"argument": {"at": 0, "value": true}}]}`:   "[6]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": "True"}}]}`: "[7]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": 1}}]}`:      "[8]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": null}}]}`:   "[9]",
	} {
		if got := found(t, "python", query, project); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}
}
