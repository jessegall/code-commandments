package rules

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/custom"
	_ "github.com/jessegall/code-commandments/registry"
)

// sample is a PHP file marking what NoDumpDetector must flag and what it must leave alone.
const sample = `<?php

class Shows
{
    public function show()
    {
        // @sin NoDumpDetector
        dump($this);
        // @righteous NoDumpDetector
        $this->render();
    }
}
`

// project is a project holding two rules of its own and a sample marking one of them.
func project(t *testing.T) string {
	t.Helper()

	root := t.TempDir()
	for path, contents := range map[string]string{
		".commandments/custom/NoDumpDetector.json":   ruleFlagging("no-dump", "dump"),
		".commandments/custom/NoRenderDetector.json": ruleFlagging("no-render", "render"),
		".commandments/custom/samples/Shows.php":     sample,
	} {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func ruleFlagging(sin, call string) string {
	return `{"engine": "backend", "sin": {"name": "` + sin + `", "description": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"name": "` + call + `"}]}}`
}

// run runs rule in the project and answers what it said and its exit code.
func run(t *testing.T, root string, args ...string) (string, int) {
	t.Helper()

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })

	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}

	var out, errs bytes.Buffer

	code, err := Command{}.Run(cli.InputOf("rule", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatal(err)
	}

	return out.String() + errs.String(), code
}

func TestExplainShowsTheTreeARuleReads(t *testing.T) {
	root := project(t)

	said, code := run(t, root, "explain", ".commandments/custom/samples/Shows.php", "--line=8")
	if code != 0 || !strings.Contains(said, "Expr_FuncCall  [call]") || !strings.Contains(said, `name="dump"`) || strings.Contains(said, "Stmt_Class") {
		t.Errorf("line 8 of the sample: %d\n%s", code, said)
	}

	whole, _ := run(t, root, "explain", ".commandments/custom/samples/Shows.php")
	if !strings.Contains(whole, `Stmt_Class  [type-declaration]  field=stmts  name="Shows"`) || !strings.Contains(whole, "Expr_MethodCall") {
		t.Errorf("the whole sample:\n%s", whole)
	}

	if said, code := run(t, root, "explain", ".commandments/custom/samples/Shows.php", "--line=99"); code != cli.Refused || !strings.Contains(said, "No node starts on line 99") {
		t.Errorf("a line holding nothing: %d\n%s", code, said)
	}
}

func TestTryRunsOneRuleWithoutTurningItOn(t *testing.T) {
	root := project(t)

	for _, named := range []string{"NoDump", "NoDumpDetector", ".commandments/custom/NoDumpDetector.json"} {
		said, code := run(t, root, "try", named, ".commandments/custom/samples")
		if code != 0 || !strings.Contains(said, "Shows.php:8  dump($this);") || !strings.Contains(said, "1 match(es) of NoDumpDetector") {
			t.Errorf("%s: %d\n%s", named, code, said)
		}
	}

	if said, code := run(t, root, "try", "NoSuch", "."); code != cli.Refused || !strings.Contains(said, "NoDumpDetector, NoRenderDetector") {
		t.Errorf("an unknown rule names the project's: %d\n%s", code, said)
	}
}

func TestProveHoldsEachRuleToTheSamplesMarks(t *testing.T) {
	root := project(t)

	said, code := run(t, root, "prove")
	if code != cli.Refused || !strings.Contains(said, "✓\033[0m NoDumpDetector") || !strings.Contains(said, "✗\033[0m NoRenderDetector") ||
		!strings.Contains(said, "flagged unmarked code at .commandments/custom/samples/Shows.php:10") {
		t.Errorf("one rule proven, one not: %d\n%s", code, said)
	}

	if err := os.Remove(filepath.Join(root, ".commandments/custom/NoRenderDetector.json")); err != nil {
		t.Fatal(err)
	}

	if said, code := run(t, root, "prove"); code != 0 || strings.Contains(said, "no `@righteous`") {
		t.Errorf("every rule proven: %d\n%s", code, said)
	}
}

func TestProveFailsWhatProvesNothing(t *testing.T) {
	for name, files := range map[string]map[string]string{
		"a rule no sample marks": {".commandments/custom/NoEchoDetector.json": ruleFlagging("no-echo", "echo_it")},
		"a mark naming no rule":  {".commandments/custom/samples/Typo.php": "<?php\n// @sin NoDumDetector\necho_it(1);\n"},
		"a rule it cannot read":  {".commandments/custom/BrokenDetector.json": "{"},
	} {
		root := project(t)
		if err := os.Remove(filepath.Join(root, ".commandments/custom/NoRenderDetector.json")); err != nil {
			t.Fatal(err)
		}

		for path, contents := range files {
			if err := os.WriteFile(filepath.Join(root, path), []byte(contents), 0o644); err != nil {
				t.Fatal(err)
			}
		}

		if said, code := run(t, root, "prove"); code != cli.Refused {
			t.Errorf("%s passed: %d\n%s", name, code, said)
		}
	}
}

func TestExplainRefusesWhatIsNoSourceFile(t *testing.T) {
	root := project(t)

	for _, path := range []string{".commandments/custom/samples", "README.md", "missing.php"} {
		if said, code := run(t, root, "explain", path); code != cli.Refused || !strings.Contains(said, "no file the tool reads") {
			t.Errorf("%s: %d\n%s", path, code, said)
		}
	}
}

func TestSchemaIsTheRuleFileSchema(t *testing.T) {
	said, code := run(t, t.TempDir(), "schema")

	var schema map[string]any
	if code != 0 || json.Unmarshal([]byte(said), &schema) != nil || schema["title"] != "A code-commandments rule" {
		t.Errorf("schema: %d\n%.200s", code, said)
	}
}

func TestAFormNotGivenSaysWhich(t *testing.T) {
	for _, args := range [][]string{{}, {"explain"}, {"try", "NoDump"}} {
		if said, code := run(t, project(t), args...); code != 2 || !strings.Contains(said, "rule") {
			t.Errorf("%v: %d\n%s", args, code, said)
		}
	}
}

func TestASampleIsNoLeftoverClass(t *testing.T) {
	if classes := custom.Load(project(t)).Classes; len(classes) != 0 {
		t.Errorf("samples read as the PHP tool's leftover classes: %v", classes)
	}
}
