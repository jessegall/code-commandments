package judge

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

// ownRuleProject is a project with a rule of its own that its one source file breaks.
func ownRuleProject(t *testing.T) string {
	t.Helper()
	root := t.TempDir()

	for path, contents := range map[string]string{
		".commandments/config.json":                       `{"paths": ["src"], "detectors": ["RawSqlDetector"]}`,
		".commandments/custom/RawSqlDetector.json":        `{"engine": "backend", "sin": {"name": "raw-sql", "description": "SQL inline", "skill": "no-raw-sql"}, "find": {"select": "call", "where": [{"name": "select"}]}}`,
		".commandments/custom/skills/no-raw-sql/SKILL.md": "---\nname: No raw SQL\ndescription: before a query.\n---\n",
		"src/Orders.php":                                  "<?php\nnamespace App;\nfinal class Orders { public function all(): array { return DB::select('x'); } }\n",
	} {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestAFindingOfTheProjectsOwnRuleIsMarkedAsItsOwn(t *testing.T) {
	root := ownRuleProject(t)

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	var out, errs bytes.Buffer

	code, err := Command{}.Run(cli.InputOf("judge", "src", "--no-checklist", "--sin=raw-sql", "--parallel=1"), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatal(err)
	}

	said := out.String()

	if code == 0 || !strings.Contains(said, "[RawSqlDetector (custom)]") || !strings.Contains(said, "Rules marked `(custom)` are THIS project's own") {
		t.Errorf("exit %d\n%s\n%s", code, said, errs.String())
	}

	out.Reset()
	if _, err := (Command{}).Run(cli.InputOf("judge", "--list"), cli.Console{Out: &out, Err: &errs}); err != nil || !strings.Contains(out.String(), "RawSqlDetector (custom)") {
		t.Errorf("not listed as the project's own: %v\n%s", err, out.String())
	}
}

// layeredProject is a project whose own rules read its declared layers, its test code and its signatures.
func layeredProject(t *testing.T) string {
	t.Helper()
	root := t.TempDir()

	for path, contents := range map[string]string{
		".commandments/config.json": `{"paths": ["src", "tests"], "detectors": ["DomainQueriesDetector", "LongParametersDetector"],
			"configure": {"backend/NamespaceDependencyDetector": [{"layer": ["App\\Domain"]}, {"layer": ["App\\Http", ["App\\Domain"]]}]}}`,
		".commandments/custom/DomainQueriesDetector.json": `{"engine": "backend", "sin": {"name": "domain-queries", "description": "The domain queries the database.", "skill": "backend/absence"},
			"find": {"select": "function", "where": [{"layer": "App\\Domain"}, {"calls": {"resolvesLike": "*DB", "of": "child:class"}}], "reject": [{"testCode": true}]}}`,
		".commandments/custom/LongParametersDetector.json": `{"engine": "backend", "sin": {"name": "long-parameters", "description": "Too many parameters.", "skill": "backend/absence"},
			"find": {"select": "function", "where": [{"parameters": {"atLeast": 3}}]}}`,
		"src/Domain/Orders.php":       "<?php\nnamespace App\\Domain;\nfinal class Orders {\n    public function all(): array { return DB::select('x'); }\n    public function one(int $a, int $b, int $c): int { return $a; }\n}\n",
		"src/Http/Page.php":           "<?php\nnamespace App\\Http;\nfinal class Page { public function show(): array { return DB::select('y'); } }\n",
		"tests/Domain/OrdersTest.php": "<?php\nnamespace App\\Domain;\nfinal class OrdersTest { public function testAll(): array { return DB::select('z'); } }\n",
	} {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestJudgeRunsTheProjectsOwnRulesOverItsLayersAndTests(t *testing.T) {
	root := layeredProject(t)

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	for sin, want := range map[string]string{"domain-queries": "Orders.php:4", "long-parameters": "Orders.php:5"} {
		var out, errs bytes.Buffer

		code, err := Command{}.Run(cli.InputOf("judge", ".", "--no-checklist", "--sin="+sin, "--parallel=1"), cli.Console{Out: &out, Err: &errs})
		if err != nil {
			t.Fatal(err)
		}

		said := out.String()
		if code == 0 || !strings.Contains(said, want) || strings.Contains(said, "Page.php") || strings.Contains(said, "OrdersTest.php") ||
			strings.Count(said, ".php:") != 1 {
			t.Errorf("%s: exit %d\n%s\n%s", sin, code, said, errs.String())
		}
	}
}
