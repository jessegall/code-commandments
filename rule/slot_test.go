package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

// Which SLOT a node fills is the one axis of place the catalogue never had: `where it sits` answers ancestry and
// order, and neither says whether this call stands as the question a branch decides by or was handed to something
// else as an argument. The tree says it on every node, in two words — the slot of its parent, and what the node
// itself is — and a rule could ask for neither.

// TestARuleAsksWhichSlotANodeFills holds the slot against the look-alike the other checks cannot separate: the
// same call, in the same method, standing as a condition and handed as an argument.
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
		// The call the branch decides by, and the same call handed on; both are inside the branch, both are calls
		// of the same method, and nothing but the slot tells them apart.
		`{"select": "call", "where": [{"field": "cond"}]}`:  "[9]",
		`{"select": "call", "where": [{"field": "value"}]}`: "[10]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestARuleAsksWhatANodeIs holds the role, which is how a rule says `a declaration` with no kind named per
// language: the same neutral kind, a function, is a member where a type declares it and an expression where it is
// a value handed around, and no other check in the catalogue tells those two apart.
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

// TestARoleTheContractDoesNotFixSaysWhich holds the closed set, since the roles are the contract's own words.
func TestARoleTheContractDoesNotFixSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"role": "declaration"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of statement, expression") {
		t.Errorf("role declaration: %v", err)
	}
}
