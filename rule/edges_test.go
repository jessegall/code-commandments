package rule_test

import (
	"fmt"
	"strings"
	"testing"

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
		// nesting counts through a closure; a call inside a closure is the closure's own
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 2}}]}`: "[18]",
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
	python := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/boxes.py": strings.Join([]string{
		"class Outer:",
		"    class Inner:",
		"        def hidden(self):",
		"            pass",
		"",
		"    def shown(self, *args, **kwargs):",
		"        return [x for x in args]",
		"",
		"def build():",
		"    return Outer.Inner()",
		"",
		"def lonely(a, b=None, *, c):",
		"    return a",
		"",
	}, "\n")})

	typescript := frontendtest.FromSource(t, map[string]string{"src/box.ts": strings.Join([]string{
		"export class Box {",
		"    static make(size?: number): Box { return new Box(); }",
		"    open(): void { this.close?.(); }",
		"    close(): void {}",
		"}",
		"const made = Box.make(1);",
		"",
	}, "\n")})

	for _, each := range []typed{
		// a nested class's members are its own
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 2}}]}`, "[1]"},
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 3}}]}`, "[]"},
		// starred, keyword-only and defaulted parameters count once each, self among them
		{"python", `{"select": "function", "where": [{"parameters": {"atLeast": 3}}]}`, "[6 12]"},
		{"python", `{"select": "parameter", "where": [{"unused": true}]}`, "[3 6 6 12 12]"},
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
	component := frontendtest.FromSource(t, map[string]string{"src/Cart.vue": strings.Join([]string{
		"<template>",
		"  <button @click=\"add\">Add</button>",
		"</template>",
		"",
		"<script setup lang=\"ts\">",
		"function add(item: string, count: number, note: string): void {",
		"    console.log(item);",
		"}",
		"</script>",
		"",
	}, "\n")})

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
	built := csharptest.FromSource(t, map[string]string{"Shelf.cs": strings.Join([]string{
		"using System;",
		"using System.Collections.Generic;",
		"[SerializableAttribute] class Shelf : List<int>, IDisposable {",
		"    public void Dispose() { }",
		"    int Count(int a, int b = 1, params int[] rest) => a;",
		"    void Walk() { foreach (var x in this) { foreach (var y in this) { foreach (var z in this) { Console.WriteLine(z); } } } }",
		"}",
		"record Point(int X, int Y);",
		"",
	}, "\n")})

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
