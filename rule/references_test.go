package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

// application is a PHP project of two files, one calling into the other.
var application = map[string]string{
	"app/Http/Orders.php": `<?php

namespace App\Http;

use App\Models\Repo;

class Orders
{
    public function index(Repo $repo, $unusedParam)
    {
        $repo->all();
        $date = new \DateTime('now');
        helper('a', 2, fn () => 3);
        return new Report();
    }

    private function never() { return $this->never(); }
}

class Report {}
class Orphan {}

function helper($a, $b, $c) { return $a . $b . $c; }
`,
	"app/Models/Repo.php": `<?php

namespace App\Models;

class Repo
{
    public function all() { return []; }
    public function stale() { return 1; }
}
`,
}

// phpProject is the codebase of the PHP files, each at its path under one root.
func phpProject(t *testing.T, files map[string]string) *engine.Codebase {
	t.Helper()

	root := t.TempDir()
	for path, contents := range files {
		must := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(must), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(must, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	loaded, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatalf("the PHP bridge cannot run here: %v", err)
	}

	return loaded
}

// foundAt runs the query and says where it matched, each as file:line, in order.
func foundAt(t *testing.T, engineName, query string, codebase *engine.Codebase) string {
	t.Helper()

	written := `{"engine": "` + engineName + `", "sin": {"name": "x", "description": "x", "skill": "backend/absence"}, "find": ` + query + `}`

	parsed, err := rule.Parse("XDetector", []byte(written), shipped)
	if err != nil {
		t.Fatalf("%s: %v", query, err)
	}

	var places []string
	for _, match := range parsed.Find(codebase) {
		places = append(places, fmt.Sprintf("%s:%d", filepath.Base(match.File()), match.Line()))
	}
	sort.Strings(places)

	return fmt.Sprint(places)
}

func TestARuleFollowsCallsAndReferences(t *testing.T) {
	project := phpProject(t, application)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"calls": {"name": "all"}}]}`:                   "[Orders.php:9]",
		`{"select": "function", "where": [{"calls": {"name": "helper"}}]}`:                "[Orders.php:9]",
		`{"select": "function", "where": [{"calls": {"nameLike": "*"}}]}`:                 "[Orders.php:17 Orders.php:9]",
		`{"select": "call", "where": [{"argument": {"at": 0, "is": "literal"}}]}`:         "[Orders.php:13]",
		`{"select": "call", "where": [{"argument": {"at": -1, "is": "function"}}]}`:       "[Orders.php:13]",
		`{"select": "call", "where": [{"argument": {"at": 5, "is": "literal"}}]}`:         "[]",
		`{"select": "construction", "where": [{"argument": {"at": 0, "is": "literal"}}]}`: "[Orders.php:12]",
		`{"select": "construction", "where": [{"constructs": "DateTime"}]}`:               "[Orders.php:12]",
		`{"select": "construction", "where": [{"constructs": "Date*"}]}`:                  "[Orders.php:12]",
		`{"select": "construction", "where": [{"constructs": "App\\Http\\*"}]}`:           "[Orders.php:14]",
		`{"select": "function", "where": [{"constructs": "Report"}]}`:                     "[Orders.php:9]",
		`{"select": "function", "where": [{"unused": true}]}`:                             "[Orders.php:17 Orders.php:9 Repo.php:8]",
		`{"select": "type-declaration", "where": [{"unused": true}]}`:                     "[Orders.php:21 Orders.php:7]",
		`{"select": "parameter", "where": [{"unused": true}]}`:                            "[Orders.php:9]",
		`{"select": "function", "where": [{"calledFrom": "app/Http/*"}]}`:                 "[Orders.php:23 Repo.php:7]",
		`{"select": "function", "where": [{"calledFrom": "app/Models/*"}]}`:               "[]",
	} {
		if got := foundAt(t, "backend", query, project); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestReferencesReadTheSameInEveryLanguage(t *testing.T) {
	codebases := map[string]*engine.Codebase{
		"python": pythontest.FromSource(t, map[string]string{"build.py": strings.Join([]string{
			"class Report:",
			"    pass",
			"",
			"def build(rows, unused_arg):",
			"    make(rows, 1, key=\"x\")",
			"    return Report()",
			"",
			"def make(a, b, key=None):",
			"    return a + b",
			"",
			"def stale():",
			"    return 1",
			"",
		}, "\n")}),
		"typescript": frontendtest.FromSource(t, map[string]string{"src/build.ts": strings.Join([]string{
			"class Report {}",
			"export function build(rows: number[]) {",
			"    make(rows, 1);",
			"    return new Report();",
			"}",
			"function make(a: number[], b: number) { return a; }",
			"function stale() { return 1; }",
			"",
		}, "\n")}),
		"csharp": csharptest.FromSource(t, map[string]string{"Orders.cs": strings.Join([]string{
			"class Report {}",
			"class Orders {",
			"    Report Build(int rows, int unused) { Make(rows, 1); return new Report(); }",
			"    int Make(int a, int b) => a + b;",
			"    int Stale() => 1;",
			"}",
			"",
		}, "\n")}),
	}

	for _, each := range []typed{
		{"python", `{"select": "call", "where": [{"argument": {"at": -1, "is": "literal"}}]}`, "[5]"},
		{"python", `{"select": "call", "where": [{"argument": {"at": 0, "is": "identifier"}}]}`, "[5]"},
		{"python", `{"select": "call", "where": [{"constructs": "Report"}]}`, "[6]"},
		{"python", `{"select": "function", "where": [{"constructs": "Report"}]}`, "[4]"},
		{"python", `{"select": "function", "where": [{"calls": {"name": "make"}}]}`, "[4]"},
		{"python", `{"select": "function", "where": [{"unused": true}]}`, "[4 11]"},
		{"python", `{"select": "parameter", "where": [{"unused": true}]}`, "[4 8]"},
		{"typescript", `{"select": "construction", "where": [{"constructs": "Report"}]}`, "[4]"},
		{"typescript", `{"select": "function", "where": [{"constructs": "Report"}]}`, "[2]"},
		{"typescript", `{"select": "call", "where": [{"argument": {"at": 1, "is": "literal"}}]}`, "[3]"},
		{"typescript", `{"select": "function", "where": [{"unused": true}]}`, "[2 7]"},
		{"typescript", `{"select": "parameter", "where": [{"unused": true}]}`, "[6]"},
		{"csharp", `{"select": "construction", "where": [{"constructs": "Report"}]}`, "[3]"},
		{"csharp", `{"select": "function", "where": [{"unused": true}]}`, "[3 5]"},
		{"csharp", `{"select": "type-declaration", "where": [{"unused": true}]}`, "[2]"},
		{"csharp", `{"select": "parameter", "where": [{"unused": true}]}`, "[3]"},
	} {
		if got := found(t, each.engine, each.query, codebases[each.engine]); got != each.want {
			t.Errorf("%s %s: found %s, want %s", each.engine, each.query, got, each.want)
		}
	}
}
