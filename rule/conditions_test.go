package rule_test

import (
	"testing"
)

func TestARuleAsksWhetherAConditionStandsForSomethingUnnamed(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Despatch.php": `<?php

namespace App;

class Despatch
{
    public function unnamed(Order $order, Customer $customer, Clock $clock): string
    {
        $note = 'hold';
        if ($order->isPaid() && $customer->isTrusted() && $clock->isOpen()) {
            $note = 'send';
        }

        return $note;
    }

    public function guarded(Order $order, Customer $customer, Clock $clock): string
    {
        if ($order->isPaid() && $customer->isTrusted() && $clock->isOpen()) {
            return 'send';
        }

        return 'hold';
    }

    public function ranged(int $at, int $from, int $until, int $closed): string
    {
        if ($at >= $from && $at <= $until && $at !== $closed) {
            return 'inside';
        }

        return 'outside';
    }

    public function named(Order $order, Customer $customer, Clock $clock): bool
    {
        return $order->isPaid() && $customer->isTrusted() && $clock->isOpen();
    }
}
`,
	})

	for name, want := range map[string]struct {
		method string
		fires  bool
	}{
		"a concept nobody named":       {"unnamed", true},
		"three tests of one subject":   {"ranged", false},
		"a chain the function returns": {"named", false},
		"a guard clause from the top":  {"guarded", false},
	} {
		t.Run(name, func(t *testing.T) {
			query := `{"select": "conjunction", "where": [{"unnamedCondition": true}, {"of": "enclosingFunction", "name": "` + want.method + `"}]}`
			found := foundAt(t, "backend", query, project)

			if fired := found != "[]"; fired != want.fires {
				t.Errorf("the reading fired %v on %s, and should %v: %s", fired, want.method, want.fires, found)
			}
		})
	}
}

func TestARuleAsksWhetherABranchBindsTheNameItsBodyReads(t *testing.T) {
	project := goProject(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"shop/lookup.go": `package shop

// Bound binds the name its body reads, which cannot move behind a name of its own.
func Bound(lines map[string]int, key string) int {
	if line, ok := lines[key]; ok {
		return line
	}

	return 0
}

// Plain tests a value it was handed and binds nothing.
func Plain(count int) int {
	if count > 0 {
		return count
	}

	return 0
}
`,
	})

	for method, want := range map[string]bool{"Bound": true, "Plain": false} {
		query := `{"select": "branch", "where": [{"bindsItsOwnTest": true}, {"of": "enclosingFunction", "name": "` + method + `"}]}`
		found := foundAt(t, "go", query, project)

		if binds := found != "[]"; binds != want {
			t.Errorf("%s binds its own test: %v, and it should be %v (%s)", method, binds, want, found)
		}
	}
}
