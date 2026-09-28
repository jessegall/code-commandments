package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// billing is a PHP project holding a class and its test.
var billing = map[string]string{
	"src/Billing.php": `<?php

namespace App;

class Billing
{
    /**
     * Totals the lines.
     *
     * @deprecated use sum()
     * @param array $lines
     */
    public function total(array $lines) { $sum = 0; foreach ($lines as $line) { $sum += $line; } return $sum; }

    // TODO: round the result
    public function sum(array $lines) { $sum = 0; foreach ($lines as $line) { $sum += $line; } return $sum; }

    public function add(array $lines) { $sum = 0; foreach ($lines as $line) { $sum -= $line; } return $sum; }
}
`,
	"tests/BillingTest.php": `<?php

namespace Tests;

class BillingTest
{
    public function testTotal() { return 1; }
}
`,
}

func TestARuleReadsCommentsCopiesAndTests(t *testing.T) {
	codebases := map[string]*engine.Codebase{
		"backend": phpProject(t, billing),
		"python": pythontest.FromSource(t, map[string]string{
			"shop/billing.py": strings.Join([]string{
				"def total(lines):",
				`    """Totals the lines.`,
				"",
				"    .. deprecated:: 2.0",
				"       Use sum_up.",
				"",
				"    Args:",
				"        lines: the lines.",
				"",
				"    :raises ValueError: when empty.",
				`    """`,
				"    return sum(lines)",
				"",
				"# TODO: remove",
				"def sum_up(lines):",
				"    return sum(lines)",
				"",
			}, "\n"),
			"tests/test_billing.py": "def test_total():\n    assert True\n",
		}),
		"typescript": frontendtest.FromSource(t, map[string]string{
			"src/billing.ts": strings.Join([]string{
				"/**",
				" * @deprecated use sum",
				" */",
				"export function total(lines: number[]) { return lines.length; }",
				"// TODO: later",
				"export function sum(lines: number[]) { return lines.length; }",
				"",
			}, "\n"),
			"src/billing.spec.ts": "export function check() { return 1; }\n",
		}),
		"csharp": csharptest.FromSource(t, map[string]string{"Billing.cs": strings.Join([]string{
			"class Billing {",
			"    /// <summary>Totals.</summary>",
			"    /// <param name=\"a\">A.</param>",
			"    int Total(int a) { return a + 1; }",
			"    // TODO: go",
			"    int Sum(int a) { return a + 1; }",
			"}",
			"",
		}, "\n")}),
	}

	for _, each := range []typed{
		{"backend", `{"select": "function", "where": [{"commentLike": "*TODO*"}]}`, "[Billing.php:16]"},
		{"backend", `{"select": "function", "where": [{"commentLike": "*Totals*lines*"}]}`, "[Billing.php:13]"},
		{"backend", `{"select": "function", "where": [{"docTag": "deprecated"}]}`, "[Billing.php:13]"},
		{"backend", `{"select": "function", "where": [{"docTag": "@param"}]}`, "[Billing.php:13]"},
		{"backend", `{"select": "function", "where": [{"docTag": "return"}]}`, "[]"},
		{"backend", `{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`, "[Billing.php:13 Billing.php:16]"},
		{"backend", `{"select": "function", "where": [{"duplicated": {"atMost": 1}}]}`, "[Billing.php:18 BillingTest.php:7]"},
		{"backend", `{"select": "type-declaration", "where": [{"testCode": true}]}`, "[BillingTest.php:5]"},
		{"backend", `{"select": "type-declaration", "where": [{"testCode": false}]}`, "[Billing.php:5]"},

		{"python", `{"select": "function", "where": [{"docTag": "deprecated"}]}`, "[billing.py:1]"},
		{"python", `{"select": "function", "where": [{"docTag": "args"}]}`, "[billing.py:1]"},
		{"python", `{"select": "function", "where": [{"docTag": "raises"}]}`, "[billing.py:1]"},
		{"python", `{"select": "function", "where": [{"commentLike": "*TODO*"}]}`, "[billing.py:15]"},
		{"python", `{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`, "[billing.py:1 billing.py:15]"},
		{"python", `{"select": "function", "where": [{"testCode": true}]}`, "[test_billing.py:1]"},

		{"typescript", `{"select": "function", "where": [{"docTag": "deprecated"}]}`, "[billing.ts:4]"},
		{"typescript", `{"select": "function", "where": [{"commentLike": "*TODO*"}]}`, "[billing.ts:6]"},
		{"typescript", `{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`, "[billing.ts:4 billing.ts:6]"},
		{"typescript", `{"select": "function", "where": [{"testCode": true}]}`, "[billing.spec.ts:1]"},

		{"csharp", `{"select": "function", "where": [{"docTag": "summary"}]}`, "[Billing.cs:4]"},
		{"csharp", `{"select": "function", "where": [{"docTag": "param"}]}`, "[Billing.cs:4]"},
		{"csharp", `{"select": "function", "where": [{"commentLike": "*TODO*"}]}`, "[Billing.cs:6]"},
		{"csharp", `{"select": "function", "where": [{"duplicated": {"atLeast": 2}}]}`, "[Billing.cs:4 Billing.cs:6]"},
	} {
		if got := foundAt(t, each.engine, each.query, codebases[each.engine]); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}
