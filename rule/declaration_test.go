package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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
		`{"select": "call", "where": [{"of": "declaration", "is": "function"}]}`:                "[tills.py:11 tills.py:15]",
		`{"select": "call", "where": [{"of": "declaration", "hasAnnotation": "staticmethod"}]}`: "[tills.py:15]",
		`{"select": "call", "where": [{"of": "declaration", "nameLike": "open_drawer"}]}`:       "[tills.py:11]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "call", "where": [{"of": "receiver", "is": "identifier"}]}`:            "[]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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

func TestTheReceiverTargetReadsTheSameWayInCSharp(t *testing.T) {
	project := csharptest.FromSource(t, map[string]string{"Shop/Checkout.cs": `namespace Shop;

public sealed class OrderRepository
{
    public void Save() { }
}

public sealed class Checkout
{
    private readonly OrderRepository orders = new();

    public void Place()
    {
        orders.Save();
        Helper();
    }

    private void Helper() { }
}
`})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"of": "receiver", "typeLike": "*OrderRepository"}]}`: "[Checkout.cs:14]",
		`{"select": "call", "where": [{"of": "receiver", "is": "identifier"}]}`:             "[Checkout.cs:14]",
	} {
		if got := foundAt(t, "csharp", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "branch", "where": [{"of": "condition", "is": "call"}]}`:       "[decide.py:6]",
		`{"select": "branch", "where": [{"of": "condition", "is": "identifier"}]}`: "[decide.py:9]",
		`{"select": "loop", "where": [{"of": "condition", "is": "identifier"}]}`:   "[]",
	} {
		if got := foundAt(t, "python", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "function", "where": [{"of": "returnType", "resolvesLike": "App\\Lines"}]}`:     "[Order.php:7]",
		`{"select": "function", "where": [{"of": "returnType", "name": "int"}]}`:                    "[Order.php:12 Order.php:22]",
		`{"select": "function", "where": [{"of": "returnType", "nameLike": "*"}]}`:                  "[Order.php:12 Order.php:22 Order.php:7]",
		`{"select": "parameter", "where": [{"of": "parameterType", "resolvesLike": "App\\Lines"}]}`: "[Order.php:22]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}

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
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "/admin*"}}]}`: "[Routes.php:21]",
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "*hidden"}}]}`: "[]",
		`{"select": "function", "where": [{"of": "annotation:Route", "argument": {"at": 0, "textLike": "*"}}]}`:       "[Routes.php:21 Routes.php:24]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s:\n got %s\nwant %s", query, got, want)
		}
	}
}
