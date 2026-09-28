package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// Each language's bases, parameters and receivers read as the language writes them.
func TestBasesParametersAndReceiversReadAsTheirLanguageWritesThem(t *testing.T) {
	csharp := csharptest.FromSource(t, map[string]string{
		"Shop.cs": `interface IA { }
interface IB : IA { }
class C : IB { }
class K {
    string name = "";
    void F(string name) { Use(this.name); }
    void Use(string s) { System.Console.WriteLine(s); }
}
interface IShape { int Area(int scale); }
record Point(int X, int Y);
`,
		"Helpers.cs": "static class Helpers { public static int Do() => 1; }\nclass User { public int X() => Helpers.Do(); }\n",
	})

	python := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/kinds.py": `from typing import Generic, Protocol, TypeVar
T = TypeVar("T")
class Shows(Protocol[T]):
    pass
class Repo(Generic[T]):
    pass
class Users(Repo[int]):
    pass
class Empty:
    """Nothing here."""
    pass
`})

	typescript := frontendtest.FromSource(t, map[string]string{"src/each.ts": `export function each(items: number[], visit: (item: number) => void): void {
    items.forEach(visit);
}
export class Account {
    constructor(private owner: string) {}
}
`})

	for _, each := range []typed{
		// an interface's own bases are what it extends, and a class honours them through it
		{"csharp", `{"select": "type-declaration", "where": [{"extends": "IA"}]}`, "[2]"},
		{"csharp", `{"select": "type-declaration", "where": [{"implements": "IA"}]}`, "[3]"},
		// a member's name and an argument's label are no reads; a bodiless member's and a record's parameters are not judged
		{"csharp", `{"select": "parameter", "where": [{"unused": true}]}`, "[6]"},
		// a type used only as a static call's receiver is used
		{"csharp", `{"select": "type-declaration", "where": [{"unused": true}, {"file": "Helpers.cs"}]}`, "[2]"},
		// a generic base names its class
		{"python", `{"select": "type-declaration", "where": [{"typeKind": "protocol"}]}`, "[3]"},
		{"python", `{"select": "type-declaration", "where": [{"extendsAny": "Repo"}]}`, "[7]"},
		// a docstring and a bare pass declare nothing
		{"python", `{"select": "type-declaration", "where": [{"members": {"atMost": 0}}]}`, "[3 5 7 9]"},
		// a callback type's parameters are its own; a parameter property is read through the object
		{"typescript", `{"select": "function", "where": [{"parameters": {"atMost": 2}}, {"name": "each"}]}`, "[1]"},
		{"typescript", `{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`, "[]"},
		{"typescript", `{"select": "parameter", "where": [{"unused": true}]}`, "[]"},
	} {
		built := map[string]*engine.Codebase{"csharp": csharp, "python": python, "typescript": typescript}[each.engine]
		if got := found(t, each.engine, each.query, built); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}

// What the language itself calls, and what a supertype dictates, is never unused.
func TestWhatTheLanguageCallsIsNeverUnused(t *testing.T) {
	php := phpCodebase(t, "Shape.php", `<?php
namespace App;
interface Shape { public function area(int $scale): int; }
final class Square implements Shape {
    public function __construct() {}
    public function __toString(): string { return 'square'; }
    public function area(int $scale): int { return 4; }
    public function lonely(): int { return 1; }
}
`)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"unused": true}]}`:  "[8]",
		`{"select": "parameter", "where": [{"unused": true}]}`: "[]",
	} {
		if got := found(t, "backend", query, php); got != want {
			t.Errorf("php %s: found %s, want %s", query, got, want)
		}
	}

	python := pythontest.FromSource(t, map[string]string{"shapes.py": `class Square:
    def __init__(self):
        self.side = 2
    def __repr__(self):
        return 'square'
    @staticmethod
    def make(size):
        return 1
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"unused": true}]}`:  "[6]",
		`{"select": "parameter", "where": [{"unused": true}]}`: "[7]",
	} {
		if got := found(t, "python", query, python); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}

	csharp := csharptest.FromSource(t, map[string]string{"Square.cs": "class Square {\n    public Square() { }\n    public override string ToString() => \"square\";\n    int Lonely() => 1;\n}\n"})
	if got := found(t, "csharp", `{"select": "function", "where": [{"unused": true}]}`, csharp); got != "[4]" {
		t.Errorf("csharp: found %s, want [4]", got)
	}
}

// An else-if continues its if; only a branch inside a branch nests.
func TestAnElseIfLadderIsNoNesting(t *testing.T) {
	query := `{"select": "branch", "where": [{"nestedAtLeast": {"is": "branch", "count": 2}}]}`

	php := phpCodebase(t, "Ladder.php", "<?php\nfunction f($a) {\n    if ($a === 1) {\n        return 1;\n    } elseif ($a === 2) {\n        return 2;\n    } elseif ($a === 3) {\n        if ($a > 0) {\n            return 3;\n        }\n    }\n    return 0;\n}\n")
	if got := found(t, "backend", query, php); got != "[8]" {
		t.Errorf("php: found %s, want [8]", got)
	}

	python := pythontest.FromSource(t, map[string]string{"ladder.py": "def f(a):\n    if a == 1:\n        return 1\n    elif a == 2:\n        return 2\n    elif a == 3:\n        if a > 0:\n            return 3\n    return 0\n"})
	if got := found(t, "python", query, python); got != "[7]" {
		t.Errorf("python: found %s, want [7]", got)
	}

	typescript := frontendtest.FromSource(t, map[string]string{"src/ladder.ts": "export function f(a: number): number {\n    if (a === 1) {\n        return 1;\n    } else if (a === 2) {\n        return 2;\n    } else if (a === 3) {\n        if (a > 0) {\n            return 3;\n        }\n    }\n    return 0;\n}\n"})
	if got := found(t, "typescript", query, typescript); got != "[7]" {
		t.Errorf("typescript: found %s, want [7]", got)
	}

	csharp := csharptest.FromSource(t, map[string]string{"Ladder.cs": "class L {\n    int F(int a) {\n        if (a == 1) {\n            return 1;\n        } else if (a == 2) {\n            return 2;\n        } else if (a == 3) {\n            if (a > 0) {\n                return 3;\n            }\n        }\n        return 0;\n    }\n}\n"})
	if got := found(t, "csharp", query, csharp); got != "[8]" {
		t.Errorf("csharp: found %s, want [8]", got)
	}
}

// A Python class honours its Protocol and abstract bases as contracts; a plain base it only inherits from.
func TestAPythonContractIsAProtocolOrAnAbstractBase(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/contracts.py": `from abc import ABC, ABCMeta
from typing import Protocol
class Shows(Protocol): ...
class Store(ABC): ...
class Meta(metaclass=ABCMeta): ...
class Plain: ...
class Cart(Shows, Plain): ...
class Shop(Store): ...
class Deep(Shop): ...
class Styled(Meta): ...
`})

	for query, want := range map[string]string{
		`{"select": "type-declaration", "where": [{"implements": "Shows"}]}`: "[7]",
		`{"select": "type-declaration", "where": [{"implements": "Store"}]}`: "[8 9]",
		`{"select": "type-declaration", "where": [{"implements": "Meta"}]}`:  "[10]",
		`{"select": "type-declaration", "where": [{"implements": "Plain"}]}`: "[]",
		`{"select": "type-declaration", "where": [{"extendsAny": "Plain"}]}`: "[7]",
	} {
		if got := found(t, "python", query, python); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}
