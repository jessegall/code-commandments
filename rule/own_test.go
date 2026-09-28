package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

const facades = `<?php

namespace App;

use Illuminate\Support\Facades\Cache;

final class Orders
{
    public function __construct() {}

    public function all(?array $rows)
    {
        $rows = $rows ?? [];
        Cache::get('orders');
        return $rows;
    }
}
`

func TestARuleNamesItsLanguagesOwnChecks(t *testing.T) {
	php := phpCodebase(t, "Orders.php", facades)

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"php": "facadeCall"}]}`:                      "[14]",
		`{"select": "function", "where": [{"php": "constructor"}]}`:                 "[9]",
		`{"select": "function", "reject": [{"php": "constructor"}]}`:                "[11]",
		`{"select": "kind:Expr_BinaryOp_Coalesce", "where": [{"php": "coalesce"}]}`: "[13]",
		`{"select": "function", "where": [{"descendant": {"php": "coalesce"}}]}`:    "[11]",
	} {
		if got := found(t, "backend", query, php); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}

	python := pythontest.FromSource(t, map[string]string{"cart.py": "class Cart:\n    def __init__(self):\n        self.items = []\n\n    def add(self, item):\n        return item\n"})
	if got := found(t, "python", `{"select": "function", "where": [{"python": "constructor"}]}`, python); got != "[2]" {
		t.Errorf("python constructor: found %s, want [2]", got)
	}
}

func TestALanguagesOwnCheckTheRuleCannotRunSaysWhy(t *testing.T) {
	for written, reason := range map[string]string{
		`{"engine": "python", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"php": "facadeCall"}]}}`:                  "a php check needs a rule that judges php",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"php": "facadeCal"}]}}`:                  `did you mean "facadeCall"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"php": "nothing"}]}}`:                    "php offers no check \"nothing\"",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"inside": {"python": "constructor"}}]}}`: "a python check needs a rule that judges python",
		`{"engine": "frontend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"typescript": "optional"}]}}`:           "a typescript check needs a rule that judges typescript",
	} {
		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", written, err, reason)
		}
	}

	for _, written := range []string{
		`{"engine": "frontend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"vue": "optional"}]}}`,
		`{"engine": "typescript", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"vue": "component"}]}}`,
	} {
		if _, err := rule.Parse("X", []byte(written), shipped); err != nil {
			t.Errorf("%s: %v", written, err)
		}
	}
}

func TestEveryLanguagesOwnChecksRun(t *testing.T) {
	typescript := frontendtest.FromSource(t, map[string]string{
		"src/pick.ts":  "export function pick(a: number, b?: number): number | null {\n    return null;\n}\n",
		"src/Card.vue": "<template>\n  <Panel>\n    <span>x</span>\n  </Panel>\n</template>\n",
	})
	csharp := csharptest.FromSource(t, map[string]string{"Shop.cs": "interface IShows { void Show(); }\nclass Shop : IShows {\n    public void Show() { }\n    public void Close() { }\n}\n"})

	for _, each := range []typed{
		{"typescript", `{"select": "parameter", "where": [{"typescript": "optional"}]}`, "[1]"},
		{"typescript", `{"select": "return", "where": [{"descendant": {"typescript": "absence"}}]}`, "[2]"},
		{"frontend", `{"select": "kind:Element", "where": [{"vue": "component"}]}`, "[2]"},
		{"frontend", `{"select": "kind:Element", "where": [{"vue": "templateRoot"}]}`, "[2]"},
		{"csharp", `{"select": "function", "where": [{"csharp": "inherited"}]}`, "[3]"},
	} {
		built := typescript
		if each.engine == "csharp" {
			built = csharp
		}

		if got := found(t, each.engine, each.query, built); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}
