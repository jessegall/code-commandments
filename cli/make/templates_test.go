package make

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

// controller is PHP holding one of every sin a template flags.
const controller = `<?php

namespace App\Http;

class OrdersController extends Controller
{
    public function index($a, $b, $c, $d, $e)
    {
        // TODO: page it
        dd($a);
        $rows = DB::select('select * from orders');
        foreach ($a as $x) {
            foreach ($x as $y) {
                foreach ($y as $z) {
                    echo $z;
                }
            }
        }
        return $rows;
    }
}
`

// ready is the rule a template makes for the engine.
func ready(t *testing.T, template Template, engine Engine) rule.Rule {
	t.Helper()

	blueprint := Of("Ready", engine, "backend/absence", false, t.TempDir())
	blueprint.From = &template

	parsed, err := rule.Parse("ReadyDetector", []byte(RuleStub(blueprint)), skill.Slugged)
	if err != nil {
		t.Fatalf("%s on %s: %v", template.Name, engine, err)
	}

	return parsed
}

func TestEveryTemplateIsARuleOnEveryEngineItIsWrittenFor(t *testing.T) {
	if len(Templates()) < 6 {
		t.Fatalf("the ready rules are %s", templateNames())
	}

	for _, template := range Templates() {
		if template.Summary == "" || template.Sin.Description == "" || template.Sin.Rule == "" || len(template.Engines()) == 0 {
			t.Errorf("%s says too little of itself: %+v", template.Name, template)
		}

		for _, engine := range template.Engines() {
			ready(t, template, engine)
		}
	}
}

func TestEveryTemplateFlagsWhatItNames(t *testing.T) {
	path := filepath.Join(t.TempDir(), "OrdersController.php")
	if err := os.WriteFile(path, []byte(controller), 0o644); err != nil {
		t.Fatal(err)
	}

	codebase, err := scan.OneFile(path).Load()
	if err != nil {
		t.Fatalf("the PHP bridge cannot run here: %v", err)
	}

	for name, want := range map[string]string{
		"no-debug-calls":        "[10]",
		"no-todo-comments":      "[7]",
		"max-parameters":        "[7]",
		"max-function-length":   "[]",
		"max-nesting":           "[14]",
		"no-sql-in-controllers": "[11]",
	} {
		template, known := TemplateNamed(name)
		if !known {
			t.Errorf("no template %s", name)

			continue
		}

		if got := lines(ready(t, template, Backend).Find(codebase)); got != want {
			t.Errorf("%s: found %s, want %s", name, got, want)
		}
	}
}

func TestATemplateNotWrittenForTheEngineIsRefused(t *testing.T) {
	root := t.TempDir()

	if said, code := run(t, root, "NoSql", "--engine=python", "--from=no-sql-in-controllers"); code != 2 || !strings.Contains(said, "written for backend") {
		t.Errorf("python sql: %d\n%s", code, said)
	}

	if said, code := run(t, root, "NoSql", "--from=nothing"); code != 2 || !strings.Contains(said, "max-nesting") {
		t.Errorf("unknown template: %d\n%s", code, said)
	}

	said, code := run(t, root, "NoDebug", "--engine=python", "--from=no-debug-calls")
	written, _ := os.ReadFile(filepath.Join(root, ".commandments/custom/NoDebugDetector.json"))
	if code != 0 || !strings.Contains(string(written), `"breakpoint"`) || !strings.Contains(string(written), "A debugging call is left") {
		t.Errorf("python debug calls: %d\n%s\n%s", code, said, written)
	}
}

func lines(matches []engine.Match) string {
	var found []int
	for _, match := range matches {
		found = append(found, match.Line())
	}

	return fmt.Sprint(found)
}
