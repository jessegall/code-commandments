package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

const controllers = `<?php

namespace App;

use Illuminate\Routing\Controller;

interface Shows extends \Stringable {}

#[Route('/orders')]
class OrdersController extends Controller implements Shows
{
    public function show(
        ?int $id,
        $raw,
        array $rows,
    ): ?string {
        return null;
    }
    public function all() { return []; }
}

final class AdminController extends OrdersController {}

enum Status: string {}

trait Logs {}
`

// typed is one query over one engine's codebase, and the lines it should find.
type typed struct{ engine, query, want string }

func TestARuleReadsTypesAndTheirHierarchy(t *testing.T) {
	codebases := map[string]*engine.Codebase{
		"backend": phpCodebase(t, "Controllers.php", controllers),
		"python": pythontest.FromSource(t, map[string]string{"shop.py": `from enum import Enum
from typing import Protocol

class Shows(Protocol):
    def show(self) -> str: ...

class Base:
    pass

@dataclass
class Order(Base):
    def total(self, rate: float, count) -> int | None:
        return None

class Special(Order):
    pass

class Color(Enum):
    RED = 1
`}),
		"typescript": frontendtest.FromSource(t, map[string]string{"src/order.ts": `export interface Shows extends Other {}
interface Other {}
class Base {}
export class Order extends Base implements Shows {
    @Watch() total(rate: number, count): string | null { return null; }
}
enum Color { Red }
`}),
		"csharp": csharptest.FromSource(t, map[string]string{"Order.cs": `namespace N;
[Serializable] class Order : Base, IShows { string? Total(int rate) => null; }
class Base : IOther {}
interface IShows : IOther {}
interface IOther {}
enum E { A }
record R(int X);
struct S {}
class Special : Order {}
`}),
	}

	for _, each := range []typed{
		{"backend", `{"select": "type-declaration", "where": [{"extends": "Controller"}]}`, "[9]"},
		{"backend", `{"select": "type-declaration", "where": [{"extends": "Illuminate\\Routing\\Controller"}]}`, "[9]"},
		{"backend", `{"select": "type-declaration", "where": [{"extends": "\\App\\OrdersController"}]}`, "[22]"},
		{"backend", `{"select": "type-declaration", "where": [{"extendsAny": "Controller"}]}`, "[9 22]"},
		{"backend", `{"select": "type-declaration", "where": [{"extendsAny": "Other\\Controller"}]}`, "[]"},
		{"backend", `{"select": "type-declaration", "where": [{"implements": "Shows"}]}`, "[9 22]"},
		{"backend", `{"select": "type-declaration", "where": [{"implements": "Stringable"}]}`, "[9 22]"},
		{"backend", `{"select": "type-declaration", "where": [{"typeKind": "interface"}]}`, "[7]"},
		{"backend", `{"select": "type-declaration", "where": [{"typeKind": "class"}]}`, "[9 22]"},
		{"backend", `{"select": "type-declaration", "where": [{"typeKind": "enum"}]}`, "[24]"},
		{"backend", `{"select": "type-declaration", "where": [{"typeKind": "trait"}]}`, "[26]"},
		{"backend", `{"select": "type-declaration", "where": [{"hasAnnotation": "Route"}]}`, "[9]"},
		{"backend", `{"select": "type-declaration", "where": [{"hasAnnotation": "App\\Route"}]}`, "[9]"},
		{"backend", `{"select": "function", "where": [{"returnType": "?string"}]}`, "[12]"},
		{"backend", `{"select": "function", "where": [{"returnType": "?*"}]}`, "[12]"},
		{"backend", `{"select": "function", "where": [{"returnType": "string"}]}`, "[]"},
		{"backend", `{"select": "function", "reject": [{"returnType": "*"}]}`, "[19]"},
		{"backend", `{"select": "parameter", "where": [{"parameterType": "array"}]}`, "[15]"},
		{"backend", `{"select": "parameter", "where": [{"parameterType": "?*"}]}`, "[13]"},
		{"backend", `{"select": "parameter", "reject": [{"parameterType": "*"}]}`, "[14]"},

		{"python", `{"select": "type-declaration", "where": [{"extends": "Base"}]}`, "[10]"},
		{"python", `{"select": "type-declaration", "where": [{"extendsAny": "Base"}]}`, "[10 15]"},
		{"python", `{"select": "type-declaration", "where": [{"implements": "Base"}]}`, "[]"},
		{"python", `{"select": "type-declaration", "where": [{"typeKind": "protocol"}]}`, "[4]"},
		{"python", `{"select": "type-declaration", "where": [{"typeKind": "enum"}]}`, "[18]"},
		{"python", `{"select": "type-declaration", "where": [{"typeKind": "class"}]}`, "[7 10 15]"},
		{"python", `{"select": "type-declaration", "where": [{"hasAnnotation": "dataclass"}]}`, "[10]"},
		{"python", `{"select": "function", "where": [{"returnType": "int | None"}]}`, "[12]"},
		{"python", `{"select": "function", "where": [{"returnType": "str"}]}`, "[5]"},
		{"python", `{"select": "parameter", "where": [{"parameterType": "float"}]}`, "[12]"},
		{"python", `{"select": "parameter", "reject": [{"parameterType": "*"}]}`, "[5 12 12]"},

		{"typescript", `{"select": "type-declaration", "where": [{"extends": "Base"}]}`, "[4]"},
		{"typescript", `{"select": "type-declaration", "where": [{"extends": "Shows"}]}`, "[]"},
		{"typescript", `{"select": "type-declaration", "where": [{"implements": "Shows"}]}`, "[4]"},
		{"typescript", `{"select": "type-declaration", "where": [{"implements": "Other"}]}`, "[4]"},
		{"typescript", `{"select": "type-declaration", "where": [{"extendsAny": "Other"}]}`, "[1]"},
		{"typescript", `{"select": "type-declaration", "where": [{"typeKind": "interface"}]}`, "[1 2]"},
		{"typescript", `{"select": "type-declaration", "where": [{"typeKind": "enum"}]}`, "[7]"},
		{"typescript", `{"select": "function", "where": [{"hasAnnotation": "Watch"}]}`, "[5]"},
		{"typescript", `{"select": "function", "where": [{"returnType": "string | null"}]}`, "[5]"},
		{"typescript", `{"select": "parameter", "where": [{"parameterType": "number"}]}`, "[5]"},

		{"csharp", `{"select": "type-declaration", "where": [{"extends": "Base"}]}`, "[2]"},
		{"csharp", `{"select": "type-declaration", "where": [{"extends": "IShows"}]}`, "[]"},
		{"csharp", `{"select": "type-declaration", "where": [{"extendsAny": "Base"}]}`, "[2 9]"},
		{"csharp", `{"select": "type-declaration", "where": [{"implements": "IShows"}]}`, "[2 9]"},
		{"csharp", `{"select": "type-declaration", "where": [{"implements": "N.IOther"}]}`, "[2 3 9]"},
		{"csharp", `{"select": "type-declaration", "where": [{"typeKind": "interface"}]}`, "[4 5]"},
		{"csharp", `{"select": "type-declaration", "where": [{"typeKind": "record"}]}`, "[7]"},
		{"csharp", `{"select": "type-declaration", "where": [{"typeKind": "struct"}]}`, "[8]"},
		{"csharp", `{"select": "type-declaration", "where": [{"hasAnnotation": "Serializable"}]}`, "[2]"},
		{"csharp", `{"select": "function", "where": [{"returnType": "string?"}]}`, "[2]"},
		{"csharp", `{"select": "parameter", "where": [{"parameterType": "int"}]}`, "[2 7]"},
	} {
		if got := found(t, each.engine, each.query, codebases[each.engine]); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}

func TestATypeKindTheToolDoesNotKnowSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "type-declaration", "where": [{"typeKind": "module"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of class, interface") {
		t.Errorf("typeKind module: %v", err)
	}
}
