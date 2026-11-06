package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

func TestARuleAsksWhichSlotANodeFills(t *testing.T) {
	codebase := phpCodebase(t, "Till.php", `<?php

namespace App;

final class Till
{
    public function close(Slip $slip): void
    {
        if ($slip->isPaid()) {
            $this->log($slip->isPaid());
        }
    }

    private function log(bool $paid): void {}
}
`)

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"field": "cond"}]}`:  "[9]",
		`{"select": "call", "where": [{"field": "value"}]}`: "[10]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestARuleAsksWhatANodeIs(t *testing.T) {
	codebase := phpCodebase(t, "Desk.php", `<?php

namespace App;

final class Desk
{
    public function add(int $amount): int
    {
        $twice = function (int $value): int {
            return $value * 2;
        };

        return $twice($amount);
    }
}
`)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"role": "member"}]}`:     "[7]",
		`{"select": "function", "where": [{"role": "expression"}]}`: "[9]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestARoleTheContractDoesNotFixSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"role": "declaration"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of statement, expression") {
		t.Errorf("role declaration: %v", err)
	}
}
