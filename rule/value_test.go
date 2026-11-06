package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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
		`{"select": "call", "where": [{"argument": {"at": 0, "value": true}}]}`:   "[9 12]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": "true"}}]}`: "[10]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": 1}}]}`:      "[11]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": null}}]}`:   "[13]",
		`{"select": "call", "where": [{"argument": {"at": 0, "value": 0}}]}`:      "[14]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

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
