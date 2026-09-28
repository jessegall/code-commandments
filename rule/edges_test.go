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
		// a contract three interfaces up, and the interfaces themselves honouring none
		`{"select": "type-declaration", "where": [{"implements": "Base"}]}`: "[12]",
		// a cycle of parents ends, each class in its own lineage
		`{"select": "type-declaration", "where": [{"extendsAny": "Loop1"}]}`: "[9 10]",
		`{"select": "type-declaration", "where": [{"extendsAny": "Base"}]}`:  "[6 7]",
		// a closure starts nesting again; a call inside a closure is the closure's own
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 2}}]}`: "[]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 1}}]}`: "[16 18]",
		`{"select": "function", "where": [{"calls": {"name": "helper"}}]}`:             "[17]",
		`{"select": "function", "where": [{"calls": {"name": "step"}}]}`:               "[14]",
		// a function only calling itself is unused; one called through $this is not; a closure is not judged
		`{"select": "function", "where": [{"unused": true}]}`: "[14 28 30 35 37]",
		// spacing, a comment and a name's case are no part of a body
		`{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`: "[30 35]",
		// arguments counted from either end, and none there on a call handed none
		`{"select": "call", "where": [{"argument": {"at": 0, "is": "function"}}]}`:    "[17]",
		`{"select": "call", "where": [{"argument": {"at": -1, "is": "function"}}]}`:   "[]",
		`{"select": "call", "where": [{"argument": {"at": -1, "is": "identifier"}}]}`: "[17 19]",
		// a node with no name, as a closure is, matches no pattern over names
		`{"select": "call", "where": [{"argument": {"at": 0, "nameLike": "*"}}]}`: "[19]",
		// a type's methods are their own counts
		`{"select": "type-declaration", "where": [{"complexity": {"atMost": 1}}]}`: "[5 6 7 9 10 12]",
		// the only statement of a block is its first and its last; the last has no next
		`{"select": "return", "where": [{"position": "only"}]}`:       "[26 28 32 35]",
		`{"select": "return", "where": [{"position": "first"}]}`:      "[26 28 32 35]",
		`{"select": "return", "where": [{"next": {"is": "return"}}]}`: "[]",
		// code inside a closure at file level runs when the closure does, not when the file loads
		`{"select": "call", "where": [{"topLevel": true}]}`:       "[]",
		`{"select": "assignment", "where": [{"topLevel": true}]}`: "[40]",
		// a size of what a node cannot have matches nothing
		`{"select": "call", "where": [{"parameters": {"atMost": 10}}]}`:                             "[]",
		`{"select": "function", "where": [{"arguments": {"atMost": 10}}]}`:                          "[]",
		`{"select": "call", "where": [{"members": {"atMost": 10}}]}`:                                "[]",
		`{"select": "function", "where": [{"lines": {"atMost": 1}}]}`:                               "[26 28 35 37 40]",
		`{"select": "type-declaration", "where": [{"members": {"atLeast": 6}}]}`:                    "[12]",
		`{"select": "function", "where": [{"count": {"descendant": {"is": "loop"}, "atMost": 0}}]}`: "[26 28 30 35 37 40]",
		// a reject drops what any step passes; a nested step in a reject reads the same
		`{"select": "function", "reject": [{"descendant": {"is": "loop"}}, {"hasModifier": "private"}]}`: "[30 35 37 40]",
		// a missing doc comment carries no tag, and a comment matches by expression
		`{"select": "function", "where": [{"docTag": "param"}]}`:                         "[]",
		`{"select": "function", "where": [{"descendant": {"commentMatches": "note$"}}]}`: "[30]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestALayerIsNamedInTheCaseItsLanguageReadsIt(t *testing.T) {
	built := phpCodebase(t, "Edges.php", edges)

	for declared, want := range map[string]string{`app\edge`: "[5 6 7 9 10 12]", `App\Edge`: "[5 6 7 9 10 12]", `App\Other`: "[]"} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "type-declaration", "where": [{"layer": "App\\Edge"}]}}`

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
		// a nested class's members are its own
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 2}}]}`, "[1]"},
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 3}}]}`, "[]"},
		// starred, keyword-only and defaulted parameters count once each, self among them
		{"python", `{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`, "[6 12]"},
		// self is Python's to bind, never the method's to read
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
		// an attribute named whole, Attribute and all, or without it
		`{"select": "type-declaration", "where": [{"hasAnnotation": "Serializable"}]}`:          "[3]",
		`{"select": "type-declaration", "where": [{"hasAnnotation": "SerializableAttribute"}]}`: "[3]",
		// a generic base is the type it names; an interface outside the scan is known by its kind
		`{"select": "type-declaration", "where": [{"extends": "List"}]}`:           "[3]",
		`{"select": "type-declaration", "where": [{"implements": "IDisposable"}]}`: "[3]",
		`{"select": "type-declaration", "where": [{"extends": "IDisposable"}]}`:    "[]",
		// a defaulted and a params parameter count once each; a record's are its own
		`{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`:            "[5]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 3}}]}`: "[6]",
		`{"select": "function", "where": [{"complexity": {"atLeast": 4}}]}`:            "[6]",
	} {
		if got := found(t, "csharp", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestGlobsPathsAndTypePatternsMatchWhatTheySay(t *testing.T) {
	built := phpCodebase(t, "Script.php", "<?php\n\n$sql = <<<SQL\nselect *\nfrom users where id = 1\nSQL;\n\nfunction pick(int | string $id): int|null { return null; }\n")

	for query, want := range map[string]string{
		// a glob's * runs across lines
		`{"select": "literal", "where": [{"textLike": "*where id*"}]}`: "[3]",
		// a file in no namespace has none to match
		`{"select": "function", "where": [{"namespaceLike": "*"}]}`: "[]",
		// spaces in a written type and in the pattern are both dropped
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
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call"}} {"extra": 1}`:                                                        "more follows it",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "type-declaration", "where": [{"members": {"of": "parent", "atLeast": 1}}]}}`: "judges nothing",
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

	// a checkout under a folder named tests is not test code; only what lies under the project's own is
	for query, want := range map[string]string{
		`{"select": "function", "where": [{"testCode": true}]}`:         "[CartTest.php:2]",
		`{"select": "function", "where": [{"testCode": false}]}`:        "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "shop/src/*.php"}]}`: "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "src/*.php"}]}`:      "[Cart.php:2]",
		`{"select": "function", "where": [{"file": "tests/*.php"}]}`:    "[]",
		`{"select": "function", "where": [{"file": "tests/**/*.php"}]}`: "[CartTest.php:2]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}
