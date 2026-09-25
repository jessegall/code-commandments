package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

const orders = `<?php

namespace App;

final class Orders
{
    public function all(): array
    {
        return DB::select('select * from orders');
    }

    public function each(array $ids): void
    {
        foreach ($ids as $id) {
            DB::select('select * from orders where id = ?', [$id]);
        }
    }

    public function cached(): array
    {
        return Cache::get('orders');
    }
}
`

func shipped(slug string) (skill.Skill, bool) {
	return skill.Slugged(slug)
}

func codebase(t *testing.T) *engine.Codebase {
	t.Helper()

	path := filepath.Join(t.TempDir(), "Orders.php")
	if err := os.WriteFile(path, []byte(orders), 0o644); err != nil {
		t.Fatal(err)
	}

	loaded, err := scan.Sources{source.PHP: {path}}.Load()
	if err != nil {
		t.Skipf("the PHP bridge cannot run here: %v", err)
	}

	return loaded
}

func lines(matches []engine.Match) []int {
	var found []int
	for _, match := range matches {
		found = append(found, match.Line())
	}

	return found
}

func TestARuleFindsWhatItsStepsDescribe(t *testing.T) {
	for name, want := range map[string]string{
		`{"select": "call", "where": [{"name": "select"}]}`:                                                           "[9 15]",
		`{"select": "call", "where": [{"name": "select"}, {"withinLoop": true}]}`:                                     "[15]",
		`{"select": "call", "where": [{"name": "select"}], "reject": [{"withinLoop": true}]}`:                         "[9]",
		`{"select": "call", "where": [{"nameIn": ["select", "get"]}, {"is": "function", "of": "enclosingFunction"}]}`: "[9 15 21]",
		`{"select": "function", "where": [{"descendant": {"is": "loop"}}]}`:                                           "[12]",
		`{"select": "call", "where": [{"file": "Orders.php"}, {"name": "get"}]}`:                                      "[21]",
		`{"select": "call", "where": [{"file": "Other.php"}]}`:                                                        "[]",
	} {
		written := `{"engine": "backend", "sin": {"name": "raw-sql", "description": "SQL written inline", "skill": "backend/laravel-idioms"}, "find": ` + name + `}`

		found, err := rule.Parse("RawSqlDetector", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", name, err)
		}

		if got := fmt.Sprint(lines(found.Find(codebase(t)))); got != want {
			t.Errorf("%s: found %s, want %s", name, got, want)
		}
	}
}

func TestARuleTheToolCannotRunSaysWhy(t *testing.T) {
	for written, reason := range map[string]string{
		`{"engine": "cobol", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call"}}`:                                                  "engine \"cobol\"",
		`{"engine": "backend", "sin": {"name": "x", "skill": "nope"}, "find": {"select": "call"}}`:                                                           "no skill \"nope\"",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "calls"}}`:                                               "select \"calls\"",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"name": "a", "kind": "b"}]}}`:         "makes 2",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"name": "a", "of": "grandparent"}]}}`: "of \"grandparent\"",
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call"}, "extra": 1}`:                                    "unknown field \"extra\"",
	} {
		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v", written, err)
		}
	}
}
