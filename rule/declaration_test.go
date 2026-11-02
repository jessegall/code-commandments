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

// TestARuleStepsOntoWhatABranchDecidesBy holds the condition target, and holds it to the look-alike the engine's
// own grammar names: a `for` over a collection decides nothing, since it walks what it is given, so a rule about
// what a branch asks must keep nothing for it.
func TestARuleStepsOntoWhatABranchDecidesBy(t *testing.T) {
	project := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/decide.py": `def ready() -> bool:
    return True


def work(lines: list[str], flag: bool) -> int:
    if ready():
        return 1

    if flag:
        return 2

    for line in lines:
        print(line)

    return 3
`,
	})

	for query, want := range map[string]string{
		// The branch that decides by what a call answers, and not the one deciding by a name.
		`{"select": "branch", "where": [{"of": "condition", "is": "call"}]}`:       "[decide.py:6]",
		`{"select": "branch", "where": [{"of": "condition", "is": "identifier"}]}`: "[decide.py:9]",
		// A loop over a collection decides nothing, so a question about a condition keeps it out.
		`{"select": "loop", "where": [{"of": "condition", "is": "identifier"}]}`: "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleStepsOntoWhatAComparisonTestsAgainstAConstant holds the subject target in two languages, with the one
// shape it must refuse: a comparison of two constants tests no subject, so a rule about a field's own question
// keeps nothing for it.
func TestARuleStepsOntoWhatAComparisonTestsAgainstAConstant(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/tasks.py": `from dataclasses import dataclass


@dataclass(frozen=True)
class Task:
    state: str


def started(task: Task) -> bool:
    return task.state == "started"


def always() -> bool:
    return 1 == 1
`,
	})

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"of": "subject", "declaredBy": "*Task"}]}`: "[tasks.py:10]",
		`{"select": "comparison", "where": [{"of": "subject", "is": "literal"}]}`:       "[]",
	} {
		if got := foundAt(t, "python", query, python); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}

	php := phpProject(t, map[string]string{
		"app/Orders.php": `<?php

namespace App;

class Orders
{
    public function paid(string $state): bool
    {
        return $state === 'paid';
    }

    public function always(): bool
    {
        return 1 === 1;
    }
}
`,
	})

	for query, want := range map[string]string{
		`{"select": "comparison", "where": [{"of": "subject", "is": "identifier"}]}`: "[Orders.php:9]",
		`{"select": "comparison", "where": [{"of": "subject", "is": "literal"}]}`:    "[]",
	} {
		if got := foundAt(t, "backend", query, php); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleStepsOntoTheTypeASignatureWrites holds the two type targets, which turn a glob over a type's TEXT into
// a question about the type itself: what it resolves to, what it is a kind of, what it holds. The look-alike is a
// signature that writes no type at all, which must answer no node rather than pass.
func TestARuleStepsOntoTheTypeASignatureWrites(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Lines.php": `<?php

namespace App;

use Illuminate\Support\Collection;

class Lines extends Collection
{
}
`,
		"app/Order.php": `<?php

namespace App;

class Order
{
    public function lines(): Lines
    {
        return new Lines();
    }

    public function total(): int
    {
        return 0;
    }

    public function untyped()
    {
        return null;
    }

    public function priced(Lines $lines, int $rate): int
    {
        return $rate;
    }
}
`,
	})

	for query, want := range map[string]string{
		// The function answering a collection, known through what its written type resolves to rather than through
		// the letters of the type's name.
		`{"select": "function", "where": [{"of": "returnType", "resolvesLike": "App\\Lines"}]}`: "[Order.php:7]",
		// Both the total and the priced read answer an int, which is the point of asking the type rather than
		// the name of the method.
		`{"select": "function", "where": [{"of": "returnType", "name": "int"}]}`: "[Order.php:12 Order.php:22]",
		// A function that writes no return type answers no node, so a rule about what a function returns keeps
		// nothing for it rather than keeping everything.
		`{"select": "function", "where": [{"of": "returnType", "nameLike": "*"}]}`: "[Order.php:12 Order.php:22 Order.php:7]",
		// A parameter's own written type, read the same way.
		`{"select": "parameter", "where": [{"of": "parameterType", "resolvesLike": "App\\Lines"}]}`: "[Order.php:22]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

// TestARuleStepsOntoAnAnnotationItNames holds the last of the phase's targets, and the difference it makes:
// hasAnnotation says an attribute is THERE, where this asks what it was handed. The look-alike is a declaration
// carrying a differently named annotation with the same argument, which must keep nothing.
func TestARuleStepsOntoAnAnnotationItNames(t *testing.T) {
	project := phpProject(t, map[string]string{
		"app/Routes.php": `<?php

namespace App;

use Attribute;

#[Attribute]
class Route
{
    public function __construct(public string $path) {}
}

#[Attribute]
class Menu
{
    public function __construct(public string $path) {}
}

class Admin
{
    #[Route('/admin/orders')]
    public function orders(): void {}

    #[Route('/public/prices')]
    public function prices(): void {}

    #[Menu('/admin/hidden')]
    public function hidden(): void {}
}
`,
	})

	for query, want := range map[string]string{
		// What the attribute was handed, asked of the attribute the rule names.
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "/admin*"}}]}`: "[Routes.php:21]",
		// The menu carries the same argument under another name, and a rule about routes keeps nothing for it.
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "*hidden"}}]}`: "[]",
		// Every method the attribute is on, whatever it was handed.
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "*"}}]}`: "[Routes.php:21 Routes.php:24]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
