package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// `of` could name only the places a node sits in — its parent, its function, its type — so a rule could ask
// nothing about the thing a node REACHES. The declaration target closes that: a rule steps onto the method a call
// runs or the field an access reads, and every check in the catalogue composes on it. These hold it in two
// languages, and hold it to answering no node rather than a guess where the scan has no answer.

// TestARuleStepsOntoTheDeclarationACallReaches holds the target itself: what a call runs is a declaration with
// modifiers, a file and a place, and a rule may ask any of that of it.
func TestARuleStepsOntoTheDeclarationACallReaches(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/tills.py": `class Till:
    def open_drawer(self) -> None:
        pass

    @staticmethod
    def count_floats() -> int:
        return 0


def start(till: Till) -> None:
    till.open_drawer()


def audit(till: Till) -> int:
    return till.count_floats()


def elsewhere(unknown) -> None:
    unknown.open_drawer()
`,
	})

	for query, want := range map[string]string{
		// The declaration a call reaches is a function of the project's own, so the call keeps its line.
		`{"select": "call", "where": [{"of": "declaration", "is": "function"}]}`: "[tills.py:11 tills.py:15]",
		// What that declaration carries is a question about it: only one of the two is static.
		`{"select": "call", "where": [{"of": "declaration", "hasAnnotation": "staticmethod"}]}`: "[tills.py:15]",
		// A receiver the scan gives no type reaches no declaration, so the step keeps nothing rather than
		// guessing at the only method of that name.
		`{"select": "call", "where": [{"of": "declaration", "nameLike": "open_drawer"}]}`: "[tills.py:11]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestTheDeclarationTargetReadsTheSameWayInPHP holds it to the engine rather than to one language: the same
// question over PHP answers the same way, through whichever answer that bridge gives.
func TestTheDeclarationTargetReadsTheSameWayInPHP(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Till.php": `<?php

namespace App;

class Till
{
    public function openDrawer(): void {}

    protected function countFloats(): int
    {
        return 0;
    }
}
`,
		"app/Shift.php": `<?php

namespace App;

class Shift
{
    public function start(Till $till): void
    {
        $till->openDrawer();
    }

    public function audit(Till $till): int
    {
        return $till->countFloats();
    }
}
`,
	})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"of": "declaration", "hasModifier": "public"}]}`:    "[Shift.php:9]",
		`{"select": "call", "where": [{"of": "declaration", "hasModifier": "protected"}]}`: "[Shift.php:14]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleStepsOntoTheObjectACallIsSentTo holds the receiver target, which composes every check in the catalogue
// onto what a call is sent to: a question about the OBJECT rather than about the call. No receiver must mean no
// node, never a silent pass, or a rule about calls on a repository would keep every free function too.
func TestARuleStepsOntoTheObjectACallIsSentTo(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/OrderRepository.php": `<?php

namespace App;

class OrderRepository
{
    public function save(): void {}
}
`,
		"app/Checkout.php": `<?php

namespace App;

class Checkout
{
    public function __construct(private OrderRepository $orders) {}

    public function place(): void
    {
        $this->orders->save();
        helper();
    }
}
`,
	})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"of": "receiver", "resolvesLike": "*Repository"}]}`: "[Checkout.php:11]",
		// A call of a bare name is sent to nobody, so the step keeps nothing rather than passing silently.
		`{"select": "call", "where": [{"of": "receiver", "is": "identifier"}]}`: "[]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestTheReceiverTargetReadsTheSameWayInPython holds it in a second language, where a call is its own node and the
// object is reached through what it calls.
func TestTheReceiverTargetReadsTheSameWayInPython(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/checkout.py": `class OrderRepository:
    def save(self) -> None:
        pass


def helper() -> None:
    pass


def place(orders: OrderRepository) -> None:
    orders.save()
    helper()
`,
	})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"of": "receiver", "typeLike": "*OrderRepository"}]}`: "[checkout.py:11]",
		`{"select": "call", "where": [{"of": "receiver", "is": "identifier"}]}`:             "[checkout.py:11]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
