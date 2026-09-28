package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

const report = `<?php

namespace App;

bootstrap();

class Report
{
    public function build(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                foreach ($cell as $value) {
                    try {
                        $this->write($value);
                    } catch (\Exception $e) {
                        log($e);
                    }
                }
            }
        }

        return $rows;
    }

    public function write($value): void
    {
        $format = fn ($v) => trim($v);
        echo $format($value);
    }
}
`

func TestARuleJudgesWhereANodeSits(t *testing.T) {
	built := phpCodebase(t, "Report.php", report)

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"inside": {"is": "catch"}}]}`:                                         "[21]",
		`{"select": "call", "where": [{"inside": {"is": "loop"}}]}`:                                          "[19 21]",
		`{"select": "call", "reject": [{"inside": {"is": "function"}}]}`:                                     "[5]",
		`{"select": "call", "where": [{"inside": {"name": "write"}}]}`:                                       "[32 33]",
		`{"select": "call", "where": [{"topLevel": true}]}`:                                                  "[5]",
		`{"select": "call", "where": [{"topLevel": false}]}`:                                                 "[19 21 32 33]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 1}}]}`:                       "[15 16 17]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 3}}]}`:                       "[17]",
		`{"select": "call", "where": [{"nestedAtLeast": {"is": "loop", "count": 3}}]}`:                       "[19 21]",
		`{"select": "call", "where": [{"nestedAtLeast": {"is": "loop", "count": 4}}]}`:                       "[]",
		`{"select": "loop", "where": [{"next": {"is": "return"}}]}`:                                          "[15]",
		`{"select": "branch", "where": [{"next": {"is": "loop"}}]}`:                                          "[11]",
		`{"select": "return", "where": [{"previous": {"is": "loop"}}]}`:                                      "[27]",
		`{"select": "return", "where": [{"previous": {"is": "return"}}]}`:                                    "[]",
		`{"select": "branch", "where": [{"position": "first"}]}`:                                             "[11]",
		`{"select": "return", "where": [{"position": "last"}]}`:                                              "[12 27]",
		`{"select": "return", "where": [{"position": "only"}]}`:                                              "[12]",
		`{"select": "call", "where": [{"name": "Report", "of": "closest:type-declaration"}]}`:                "[19 21 32 33]",
		`{"select": "call", "where": [{"nestedAtLeast": {"is": "loop", "count": 3}, "of": "closest:loop"}]}`: "[19 21]",
		`{"select": "call", "where": [{"descendant": {"is": "catch"}, "of": "root"}]}`:                       "[5 19 21 32 33]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestTopLevelCodeIsWhatRunsWhenAFileLoads(t *testing.T) {
	module := pythontest.FromSource(t, map[string]string{"jobs.py": `import os

setup()

def run():
    go()

class Job:
    limit = compute()

for a in rows:
    for b in a:
        use(b)
`})

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"topLevel": true}]}`:                            "[3 13]",
		`{"select": "call", "where": [{"nestedAtLeast": {"is": "loop", "count": 2}}]}`: "[13]",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 2}}]}`: "[12]",
	} {
		if got := found(t, "python", query, module); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}

	script := frontendtest.FromSource(t, map[string]string{"src/boot.ts": `start();

export function run(): void {
    go();
}

const later = () => stop();
`})

	if got := found(t, "typescript", `{"select": "call", "where": [{"topLevel": true}]}`, script); got != "[1]" {
		t.Errorf("typescript top-level calls: found %s, want [1]", got)
	}
}

func TestAPlaceTheToolCannotReadSaysWhy(t *testing.T) {
	for query, reason := range map[string]string{
		`{"select": "return", "where": [{"position": "middle"}]}`:                                   `position "middle"`,
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "count": 0}}]}`:              "count of 1 or more",
		`{"select": "loop", "where": [{"nestedAtLeast": {"is": "loop", "name": "x", "count": 2}}]}`: "makes 2",
		`{"select": "call", "where": [{"is": "loop", "of": "closest:nothing"}]}`:                    "names no neutral kind",
		`{"select": "call", "where": [{"is": "loop", "of": "sideways"}]}`:                           "closest:<kind>",
		`{"select": "call", "where": [{"inside": {"nameMatches": "("}}]}`:                           "no regular expression",
	} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": ` + query + `}`

		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", query, err, reason)
		}
	}
}
