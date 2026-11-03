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
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "type-declaration", "where": [{"typeKind": "module"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of class, interface") {
		t.Errorf("typeKind module: %v", err)
	}
}

// Absence is the one thing every one of these languages spells differently and means identically, and a rule could
// only glob the text for it: `?*` is PHP's, `*|null` catches one more spelling, and neither says anything about
// `str | None` or a C# `string?`. One check over the type object answers it in all of them, and refuses the two
// look-alikes a glob gets wrong: a union of two real types, and a type merely NAMED like absence.

// TestARuleAsksWhetherATypeAdmitsAbsence holds the reading in four languages, each with its own spelling and its
// own look-alike in the same file.
func TestARuleAsksWhetherATypeAdmitsAbsence(t *testing.T) {
	codebases := map[string]*engine.Codebase{
		"backend": phpCodebase(t, "Till.php", `<?php

namespace App;

final class Maybe {}

final class Till
{
    public function spare(): ?Money
    {
        return null;
    }

    public function orNull(): Money|null
    {
        return null;
    }

    public function held(): Money
    {
        return new Money();
    }

    public function either(): int|string
    {
        return 1;
    }

    public function named(): Maybe
    {
        return new Maybe();
    }
}
`),
		"python": pythontest.FromSource(t, map[string]string{
			"shop/__init__.py": "",
			"shop/till.py": `class Money:
    pass


class Maybe:
    pass


def spare() -> Money | None:
    return None


def quoted() -> "Money | None":
    return None


def held() -> Money:
    return Money()


def either() -> int | str:
    return 1


def named() -> Maybe:
    return Maybe()
`,
		}),
		"csharp": csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

public sealed class Money { }

public sealed class Maybe<T> { }

public sealed class Till
{
    public string? Spare() => null;

    public string Held() => "held";

    public Maybe<string> Named() => new();
}
`}),
		"typescript": frontendtest.FromSource(t, map[string]string{"src/till.ts": `export class Money {}

export class Maybe<T> {}

export function spare(): Money | null {
    return null
}

export function missing(): Money | undefined {
    return undefined
}

export function held(): Money {
    return new Money()
}

export function named(): Maybe<Money> {
    return new Maybe()
}
`}),
	}

	for _, each := range []struct {
		engine string
		query  string
		want   string
	}{
		// PHP writes absence two ways, and both are the same type.
		{"backend", `{"select": "function", "where": [{"nullable": true}]}`, "[9 14]"},
		// A union of two real types is no absence, and neither is a class called Maybe.
		{"backend", `{"select": "function", "where": [{"nullable": false}]}`, "[19 24 29]"},

		// The quoted form is the same type as the plain one, which is how Python writes a type that is not
		// defined yet, and it used to be read as a class called "Money | None".
		{"python", `{"select": "function", "where": [{"nullable": true}]}`, "[9 13]"},

		{"csharp", `{"select": "function", "where": [{"nullable": true}]}`, "[9]"},

		{"typescript", `{"select": "function", "where": [{"nullable": true}]}`, "[5 9]"},
		{"typescript", `{"select": "function", "where": [{"nullable": false}]}`, "[13 17]"},
	} {
		if got := found(t, each.engine, each.query, codebases[each.engine]); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}

// A type's SHAPE and what a collection holds are facts the bridges write and nothing read, so a rule had to glob a
// type's text for both: `*[]` for an array, `list<*>` for its element. These hold the two checks over the type
// object, where the shape is the bridge's own word and the element is the type it read rather than a name parsed
// back out of a string.

// TestARuleAsksWhatShapeATypeHas holds the shapes apart in the language whose bridge writes the most of them, and
// holds a generic that is no collection to being neither.
func TestARuleAsksWhatShapeATypeHas(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/till.ts": `export class Order {}

export function listed(): Order[] {
    return []
}

export function paired(): [number, string] {
    return [1, 'a']
}

export function one(): Order {
    return new Order()
}

export function either(): Order | number {
    return 1
}
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"typeShape": "array"}]}`: "[3]",
		`{"select": "function", "where": [{"typeShape": "tuple"}]}`: "[7]",
		`{"select": "function", "where": [{"typeShape": "named"}]}`: "[11]",
		`{"select": "function", "where": [{"typeShape": "union"}]}`: "[15]",
	} {
		if got := found(t, "typescript", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestARuleAsksWhatACollectionHolds holds the element, and holds it to saying nothing where the collection's
// element is unknown: a bag of unknowns is no bag of the type the rule asked about, and guessing one would make
// every rule about a typed collection fire on every untyped one.
func TestARuleAsksWhatACollectionHolds(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/orders.ts": `export class Order {}

export function orders(): Order[] {
    return []
}

export function anything(): unknown[] {
    return []
}

export function one(): Order {
    return new Order()
}
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"elementType": "*Order"}]}`:  "[3]",
		`{"select": "function", "where": [{"elementType": "unknown"}]}`: "[7]",
		// The one that holds nothing: a type that is no collection has no element to name.
		`{"select": "function", "where": [{"elementType": "*"}]}`: "[3 7]",
	} {
		if got := found(t, "typescript", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestAShapeTheContractDoesNotFixSaysWhich holds the closed set, since the shapes are the contract's own words and
// a rule file is written by hand.
func TestAShapeTheContractDoesNotFixSaysWhich(t *testing.T) {
	written := `{"engine": "typescript", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"typeShape": "collection"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of named, keyword") {
		t.Errorf("typeShape collection: %v", err)
	}
}

// Where a type came FROM is the fact that tells a type a human wrote from one a tool worked out, and it decides
// two things nothing else can: a rule about a type a docblock carries where the signature carries none, and the
// exemption every honesty rule needs — do not judge a type nobody wrote.

// TestARuleAsksWhereATypeCameFrom holds the origins the bridges and the engine really write: a declaration's own
// annotation, and the engine's own inference where no annotation says anything.
func TestARuleAsksWhereATypeCameFrom(t *testing.T) {
	codebase := phpCodebase(t, "Desk.php", `<?php

namespace App;

final class Slip {}

final class Desk
{
    public function make(): Slip
    {
        $slip = new Slip();

        return $slip;
    }
}
`)

	for query, want := range map[string]string{
		// The return type the method writes.
		`{"select": "function", "where": [{"typeOrigin": "written"}]}`: "[9]",
		// The construction's type is the engine's own reading, and says so rather than borrowing the word written.
		`{"select": "construction", "where": [{"typeOrigin": "inferred"}]}`: "[11]",
		`{"select": "construction", "where": [{"typeOrigin": "written"}]}`:  "[]",
	} {
		if got := found(t, "backend", query, codebase); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestATypeNobodyWroteIsLeftAlone holds the exemption as a reject, which is how an honesty rule uses it: the rule
// judges the types a human wrote and leaves the checker's own readings alone, so proving the positive alone would
// not prove the thing such a rule depends on.
func TestATypeNobodyWroteIsLeftAlone(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/till.ts": `export function total(count: number): number {
    return count + 1
}
`})

	// Every name in the body carries the type the checker read, and nobody wrote any of them.
	read := `{"select": "identifier", "where": [{"typeShape": "keyword"}]}`
	if got := found(t, "typescript", read, codebase); got == "[]" {
		t.Fatalf("%s: found nothing, and the checker types every name here", read)
	}

	spared := `{"select": "identifier", "where": [{"typeShape": "keyword"}], "reject": [{"typeOrigin": "compiler"}]}`
	if got := found(t, "typescript", spared, codebase); got != "[]" {
		t.Errorf("%s: found %s, and the checker's own readings are what the exemption spares", spared, got)
	}
}

// TestAnOriginTheContractDoesNotFixSaysWhich holds the closed set.
func TestAnOriginTheContractDoesNotFixSaysWhich(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "function", "where": [{"typeOrigin": "guessed"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "none of written, docblock") {
		t.Errorf("typeOrigin guessed: %v", err)
	}
}
