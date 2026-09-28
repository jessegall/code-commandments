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

const ledger = `<?php

namespace App;

class Ledger
{
    private array $rows = [];
    const LIMIT = 10;

    public function add(string $a, int $b, ?int $c = null, ...$rest): void
    {
        if ($a === '') {
            return;
        }

        foreach ($rest as $item) {
            try {
                record($a, $b, $c, $item);
            } catch (\Throwable $e) {
                return;
            }
        }

        $each = function ($row) {
            if ($row) {
                return 1;
            }
            return 0;
        };
    }

    public function one(): int { return count([1]); }
}
`

func TestARuleBoundsTheSizeOfANode(t *testing.T) {
	built := phpCodebase(t, "Ledger.php", ledger)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"parameters": {"atLeast": 4}}]}`:                                       "[10]",
		`{"select": "function", "where": [{"parameters": {"atMost": 0}}]}`:                                        "[32]",
		`{"select": "function", "where": [{"parameters": {"atMost": 1}}]}`:                                        "[24 32]",
		`{"select": "call", "where": [{"arguments": {"atLeast": 4}}]}`:                                            "[18]",
		`{"select": "call", "where": [{"arguments": {"atMost": 1}}]}`:                                             "[32]",
		`{"select": "function", "where": [{"lines": {"atLeast": 10}}]}`:                                           "[10]",
		`{"select": "function", "where": [{"lines": {"atMost": 1}}]}`:                                             "[32]",
		`{"select": "function", "where": [{"lines": {"atLeast": 6, "atMost": 6}}]}`:                               "[24]",
		`{"select": "type-declaration", "where": [{"lines": {"atLeast": 29}}]}`:                                   "[5]",
		`{"select": "type-declaration", "where": [{"lines": {"atLeast": 30}}]}`:                                   "[]",
		`{"select": "type-declaration", "where": [{"members": {"atLeast": 4}}]}`:                                  "[5]",
		`{"select": "type-declaration", "where": [{"members": {"is": "function", "atLeast": 2}}]}`:                "[5]",
		`{"select": "type-declaration", "where": [{"members": {"is": "function", "atLeast": 3}}]}`:                "[]",
		`{"select": "function", "where": [{"complexity": {"atLeast": 4}}]}`:                                       "[10]",
		`{"select": "function", "where": [{"complexity": {"atLeast": 2, "atMost": 2}}]}`:                          "[24]",
		`{"select": "function", "where": [{"complexity": {"atMost": 1}}]}`:                                        "[32]",
		`{"select": "function", "where": [{"count": {"descendant": {"is": "return"}, "atLeast": 2}}]}`:            "[10 24]",
		`{"select": "function", "where": [{"count": {"descendant": {"is": "return"}, "atLeast": 3}}]}`:            "[10]",
		`{"select": "function", "where": [{"count": {"child": {"is": "loop"}, "field": "stmts", "atLeast": 1}}]}`: "[10]",
		`{"select": "function", "where": [{"count": {"child": {"is": "branch"}, "atLeast": 1}}]}`:                 "[10 24]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestSizesReadTheSameInEveryLanguage(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{"cart.py": strings.Join([]string{
		"class Cart:",
		"    limit = 3",
		"",
		"    def add(self, item, *more, qty=1, **extra):",
		"        save(item, *more, qty=qty)",
		"",
		"    def clear(self):",
		"        pass",
		"",
	}, "\n")})

	typescript := frontendtest.FromSource(t, map[string]string{"src/cart.ts": strings.Join([]string{
		"export class Cart {",
		"    limit = 3;",
		"    add(item: string, ...more: string[]): void {",
		"        save(item, ...more);",
		"    }",
		"}",
		"",
	}, "\n")})

	csharp := csharptest.FromSource(t, map[string]string{"Cart.cs": strings.Join([]string{
		"class Cart",
		"{",
		"    int limit;",
		"    void Add(int a, params int[] more) { Save(a, b: more); }",
		"    void Save(int a, int[] b) { }",
		"}",
		"",
	}, "\n")})

	for _, each := range []struct{ engine, query, want string }{
		{"python", `{"select": "function", "where": [{"parameters": {"atLeast": 5}}]}`, "[4]"},
		{"python", `{"select": "function", "where": [{"parameters": {"atMost": 1}}]}`, "[7]"},
		{"python", `{"select": "call", "where": [{"arguments": {"atLeast": 3}}]}`, "[5]"},
		{"python", `{"select": "type-declaration", "where": [{"members": {"atLeast": 3}}]}`, "[1]"},
		{"python", `{"select": "type-declaration", "where": [{"members": {"is": "function", "atMost": 2}}]}`, "[1]"},
		{"typescript", `{"select": "function", "where": [{"parameters": {"atLeast": 2}}]}`, "[3]"},
		{"typescript", `{"select": "call", "where": [{"arguments": {"atLeast": 2}}]}`, "[4]"},
		{"typescript", `{"select": "type-declaration", "where": [{"members": {"atLeast": 2}}]}`, "[1]"},
		{"csharp", `{"select": "function", "where": [{"parameters": {"atLeast": 2}}]}`, "[4 5]"},
		{"csharp", `{"select": "call", "where": [{"arguments": {"atLeast": 2}}]}`, "[4]"},
		{"csharp", `{"select": "type-declaration", "where": [{"members": {"atLeast": 3}}]}`, "[1]"},
	} {
		built := map[string]*engine.Codebase{"python": python, "typescript": typescript, "csharp": csharp}[each.engine]

		if got := found(t, each.engine, each.query, built); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}

func TestASizeTheToolCannotReadSaysWhy(t *testing.T) {
	for query, reason := range map[string]string{
		`{"select": "function", "where": [{"parameters": {}}]}`:                                                        "needs atLeast, atMost or both",
		`{"select": "function", "where": [{"lines": {"atLeast": 5, "atMost": 2}}]}`:                                    "leaves nothing",
		`{"select": "call", "where": [{"arguments": {"atMost": -1}}]}`:                                                 "below zero",
		`{"select": "function", "where": [{"count": {"atLeast": 1}}]}`:                                                 "descendants or children",
		`{"select": "function", "where": [{"count": {"descendant": {"is": "loop"}, "field": "stmts", "atLeast": 1}}]}`: "narrows its children",
		`{"select": "type-declaration", "where": [{"members": {"is": "nothing", "atLeast": 1}}]}`:                      "no neutral kind",
	} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": ` + query + `}`

		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", query, err, reason)
		}
	}
}
