package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

const edges = `<?php

namespace App\Edge;

interface Base {}
interface Middle extends Base {}
interface Top extends Middle {}

class Loop1 extends Loop2 {}
class Loop2 extends Loop1 {}

class Worker implements Top
{
    public function run(array $rows)
    {
        foreach ($rows as $row) {
            array_map(function ($cell) {
                foreach ($cell as $v) {
                    helper($v);
                }
            }, $row);
        }
        return $this->step();
    }

    private function step() { return 1; }

    private function spin($n) { return $this->spin($n - 1); }

    public function   same( $x ) {
        // a note
        return $x   + 1;
    }

    public function twin($x) { return $x + 1; }

    public function none() {}
}

$boot = function () { start(); };
`

func TestTheChecksHoldAtTheirEdges(t *testing.T) {
	built := phpCodebase(t, "Edges.php", edges)

	for query, want := range map[string]string{
		`{"select": "type-declaration", "where": [{"implements": "Base"}]}`:                              "[12]",
		`{"select": "type-declaration", "where": [{"extendsAny": "Loop1"}]}`:                             "[9 10]",
		`{"select": "type-declaration", "where": [{"extendsAny": "Base"}]}`:                              "[6 7]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 2}}]}`:                   "[]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 1}}]}`:                   "[16 18]",
		`{"select": "function", "where": [{"calls": {"name": "helper"}}]}`:                               "[17]",
		`{"select": "function", "where": [{"calls": {"name": "step"}}]}`:                                 "[14]",
		`{"select": "function", "where": [{"unused": true}]}`:                                            "[14 28 30 35 37]",
		`{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`:                              "[30 35]",
		`{"select": "call", "where": [{"argument": {"at": 0, "is": "function"}}]}`:                       "[17]",
		`{"select": "call", "where": [{"argument": {"at": -1, "is": "function"}}]}`:                      "[]",
		`{"select": "call", "where": [{"argument": {"at": -1, "is": "identifier"}}]}`:                    "[17 19]",
		`{"select": "call", "where": [{"argument": {"at": 0, "nameLike": "*"}}]}`:                        "[19]",
		`{"select": "type-declaration", "where": [{"complexity": {"atMost": 1}}]}`:                       "[5 6 7 9 10 12]",
		`{"select": "return", "where": [{"position": "only"}]}`:                                          "[26 28 32 35]",
		`{"select": "return", "where": [{"position": "first"}]}`:                                         "[26 28 32 35]",
		`{"select": "return", "where": [{"next": {"is": "return"}}]}`:                                    "[]",
		`{"select": "call", "where": [{"topLevel": true}]}`:                                              "[]",
		`{"select": "assignment", "where": [{"topLevel": true}]}`:                                        "[40]",
		`{"select": "call", "where": [{"parameters": {"atMost": 10}}]}`:                                  "[]",
		`{"select": "function", "where": [{"arguments": {"atMost": 10}}]}`:                               "[]",
		`{"select": "call", "where": [{"members": {"atMost": 10}}]}`:                                     "[]",
		`{"select": "function", "where": [{"lines": {"atMost": 1}}]}`:                                    "[26 28 35 37 40]",
		`{"select": "type-declaration", "where": [{"members": {"atLeast": 6}}]}`:                         "[12]",
		`{"select": "function", "where": [{"count": {"descendant": {"is": "loop"}, "atMost": 0}}]}`:      "[26 28 30 35 37 40]",
		`{"select": "function", "reject": [{"descendant": {"is": "loop"}}, {"hasModifier": "private"}]}`: "[30 35 37 40]",
		`{"select": "function", "where": [{"docTag": "param"}]}`:                                         "[]",
		`{"select": "function", "where": [{"descendant": {"commentMatches": "note$"}}]}`:                 "[30]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestALayerIsNamedInTheCaseItsLanguageReadsIt(t *testing.T) {
	built := phpCodebase(t, "Edges.php", edges)

	for declared, want := range map[string]string{`app\edge`: "[5 6 7 9 10 12]", `App\Edge`: "[5 6 7 9 10 12]", `App\Other`: "[]"} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "type-declaration", "where": [{"layer": "App\\Edge"}]}}`

		parsed, err := rule.Parse("X", []byte(written), shipped)
		if err != nil {
			t.Fatal(err)
		}

		if got := fmt.Sprint(lines(parsed.WithLayers(map[string][]string{declared: nil}).Find(built))); got != want {
			t.Errorf("declared %s: found %s, want %s", declared, got, want)
		}
	}
}

func TestTheChecksHoldAtTheirEdgesInPythonAndTypeScript(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/boxes.py": `class Outer:
    class Inner:
        def hidden(self):
            pass

    def shown(self, *args, **kwargs):
        return [x for x in args]

def build():
    return Outer.Inner()

def lonely(a, b=None, *, c):
    return a
`})

	typescript := frontendtest.FromSource(t, map[string]string{"src/box.ts": `export class Box {
    static make(size?: number): Box { return new Box(); }
    open(): void { this.close?.(); }
    close(): void {}
}
const made = Box.make(1);
`})

	for _, each := range []typed{
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 2}}]}`, "[1]"},
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 3}}]}`, "[]"},
		{"python", `{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`, "[6 12]"},
		{"python", `{"select": "parameter", "where": [{"unused": true}]}`, "[6 12 12]"},
		{"python", `{"select": "call", "where": [{"constructs": "Inner"}]}`, "[10]"},
		{"python", `{"select": "call", "where": [{"constructs": "shop.boxes.Outer.Inner"}]}`, "[10]"},
		{"python", `{"select": "function", "where": [{"topLevel": true}]}`, "[9 12]"},
		{"typescript", `{"select": "function", "where": [{"parameters": {"atMost": 0}}]}`, "[3 4]"},
		{"typescript", `{"select": "function", "where": [{"returnType": "Box"}]}`, "[2]"},
		{"typescript", `{"select": "parameter", "where": [{"parameterType": "number"}]}`, "[2]"},
		{"typescript", `{"select": "function", "where": [{"constructs": "Box"}]}`, "[2]"},
		{"typescript", `{"select": "call", "where": [{"topLevel": true}]}`, "[6]"},
		{"typescript", `{"select": "type-declaration", "where": [{"members": {"is": "function", "atLeast": 3}}]}`, "[1]"},
	} {
		built := python
		if each.engine == "typescript" {
			built = typescript
		}

		if got := found(t, each.engine, each.query, built); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}

func TestAFrontendRuleReadsAComponentsScript(t *testing.T) {
	component := frontendtest.FromSource(t, map[string]string{"src/Cart.vue": `<template>
  <button @click="add">Add</button>
</template>

<script setup lang="ts">
function add(item: string, count: number, note: string): void {
    console.log(item);
}
</script>
`})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"name": "log"}, {"descendant": {"name": "console"}}]}`: "[7]",
		`{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`:                   "[6]",
		`{"select": "function", "where": [{"returnType": "void"}]}`:                           "[6]",
	} {
		for _, engineName := range []string{"frontend", "typescript"} {
			if got := found(t, engineName, query, component); got != want {
				t.Errorf("%s %s: found %s, want %s", engineName, query, got, want)
			}
		}
	}
}

func TestTheChecksHoldAtTheirEdgesInCSharp(t *testing.T) {
	built := csharptest.FromSource(t, map[string]string{"Shelf.cs": `using System;
using System.Collections.Generic;
[SerializableAttribute] class Shelf : List<int>, IDisposable {
    public void Dispose() { }
    int Count(int a, int b = 1, params int[] rest) => a;
    void Walk() { foreach (var x in this) { foreach (var y in this) { foreach (var z in this) { Console.WriteLine(z); } } } }
}
record Point(int X, int Y);
`})

	for query, want := range map[string]string{
		`{"select": "type-declaration", "where": [{"hasAnnotation": "Serializable"}]}`:          "[3]",
		`{"select": "type-declaration", "where": [{"hasAnnotation": "SerializableAttribute"}]}`: "[3]",
		`{"select": "type-declaration", "where": [{"extends": "List"}]}`:                        "[3]",
		`{"select": "type-declaration", "where": [{"implements": "IDisposable"}]}`:              "[3]",
		`{"select": "type-declaration", "where": [{"extends": "IDisposable"}]}`:                 "[]",
		`{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`:                     "[5]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 3}}]}`:          "[6]",
		`{"select": "function", "where": [{"complexity": {"atLeast": 4}}]}`:                     "[6]",
	} {
		if got := found(t, "csharp", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestGlobsPathsAndTypePatternsMatchWhatTheySay(t *testing.T) {
	built := phpCodebase(t, "Script.php", "<?php\n\n$sql = <<<SQL\nselect *\nfrom users where id = 1\nSQL;\n\nfunction pick(int | string $id): int|null { return null; }\n")

	for query, want := range map[string]string{
		`{"select": "literal", "where": [{"textLike": "*where id*"}]}`:        "[3]",
		`{"select": "function", "where": [{"namespaceLike": "*"}]}`:           "[]",
		`{"select": "function", "where": [{"returnType": "int | null"}]}`:     "[8]",
		`{"select": "parameter", "where": [{"parameterType": "int|string"}]}`: "[8]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}

	project := phpProject(t, map[string]string{
		"app/Http/Controllers/Orders.php": "<?php\nnamespace App\\Http\\Controllers;\nfunction show() { return \\App\\Models\\count_orders(); }\n",
		"app/Models/Counts.php":           "<?php\nnamespace App\\Models;\nfunction count_orders() { return 1; }\n",
	})
	for pattern, want := range map[string]string{"app/Http/**": "[Counts.php:3]", "app/Http/*": "[]", "Http/**/*.php": "[Counts.php:3]"} {
		if got := foundAt(t, "backend", `{"select": "function", "where": [{"calledFrom": "`+pattern+`"}]}`, project); got != want {
			t.Errorf("called from %s: found %s, want %s", pattern, got, want)
		}
	}

	for written, reason := range map[string]string{
		`{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "call"}} {"extra": 1}`:                                                        "more follows it",
		`{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "type-declaration", "where": [{"members": {"of": "parent", "atLeast": 1}}]}}`: "judges nothing",
	} {
		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", written, err, reason)
		}
	}
}

func TestAPathIsReadFromTheFolderJudged(t *testing.T) {
	root := filepath.Join(t.TempDir(), "tests", "shop")
	for path, contents := range map[string]string{
		"src/Cart.php":            "<?php\nfunction add() {}\n",
		"tests/Unit/CartTest.php": "<?php\nfunction test_add() {}\n",
	} {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	project, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatalf("the PHP bridge cannot run here: %v", err)
	}

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"testCode": true}]}`:                                   "[CartTest.php:2]",
		`{"select": "function", "where": [{"testCode": false}]}`:                                  "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "shop/src/*.php"}]}`:                           "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "src/*.php"}]}`:                                "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "tests/*.php"}]}`:                              "[]",
		`{"select": "function", "where": [{"file": "tests/**/*.php"}]}`:                           "[CartTest.php:2]",
		`{"select": "function", "where": [{"fileMatches": "(^|/)(src|lib)/[A-Z][a-z]+\\.php$"}]}`: "[Cart.php:2]",
		`{"select": "function", "where": [{"fileMatches": "(Spec|Test)\\.php$"}]}`:                "[CartTest.php:2]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestAPathPatternReadsFromTheProjectWhateverTheDepthJudged(t *testing.T) {
	project := t.TempDir()
	for path, contents := range map[string]string{
		".git/HEAD":                      "ref: refs/heads/main\n",
		"app/Actions/Trips/GetTrips.php": "<?php\nfunction trips() {}\n",
		"app/Models/Trip.php":            "<?php\nfunction trip() {}\n",
	} {
		file := filepath.Join(project, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	for _, judged := range []string{"app", "app/Actions", "app/Actions/Trips"} {
		codebase, err := scan.Walk([]string{filepath.Join(project, judged)}, source.Excluded{}).Load()
		if err != nil {
			t.Fatalf("the PHP bridge cannot run here: %v", err)
		}
		for query, want := range map[string]string{
			`{"select": "function", "where": [{"fileMatches": "^app/Actions/"}]}`: "[GetTrips.php:2]",
			`{"select": "function", "where": [{"file": "app/Actions/**/*.php"}]}`: "[GetTrips.php:2]",
		} {
			if got := foundAt(t, "backend", query, codebase); got != want {
				t.Errorf("judged from %s, %s: found %s, want %s", judged, query, got, want)
			}
		}
	}
}

func TestARuleTellsOperatorsApart(t *testing.T) {
	money := frontendtest.FromSource(t, map[string]string{"src/money.ts": `export function totals(price: { amount: number }, rate: number, fallback: number) {
    const scaled = price.amount * rate;
    const safe = price.amount ?? fallback;
    const share = (scaled / safe) * 100;
    let running = 0;
    running += scaled;

    return { scaled, safe, share, running };
}
`})

	for _, each := range []typed{
		{"typescript", `{"select": "kind:BinaryExpression", "where": [{"operator": "*"}]}`, "[2 4]"},
		{"typescript", `{"select": "kind:BinaryExpression", "where": [{"operator": "??"}]}`, "[3]"},
		{"typescript", `{"select": "kind:BinaryExpression", "where": [{"operatorIn": ["*", "/"]}]}`, "[2 4 4]"},
		{"typescript", `{"select": "kind:BinaryExpression", "where": [{"operator": "+="}]}`, "[6]"},
	} {
		if got := found(t, each.engine, each.query, money); got != each.want {
			t.Errorf("%s: found %s, want %s", each.query, got, each.want)
		}
	}
}

func TestARuleReadsAnExpressionsType(t *testing.T) {
	money := frontendtest.FromSource(t, map[string]string{"src/money.ts": `export function totals(price: { amount: number }, label: { amount: string }) {
    const paid = price.amount;
    const shown = label.amount;

    return { paid, shown };
}
`})

	for _, each := range []typed{
		{"typescript", `{"select": "kind:PropertyAccessExpression", "where": [{"type": "number"}]}`, "[2]"},
		{"typescript", `{"select": "kind:PropertyAccessExpression", "where": [{"type": "string"}]}`, "[3]"},
		{"typescript", `{"select": "kind:PropertyAccessExpression", "where": [{"typeLike": "str*"}]}`, "[3]"},
	} {
		if got := found(t, each.engine, each.query, money); got != each.want {
			t.Errorf("%s: found %s, want %s", each.query, got, each.want)
		}
	}
}
