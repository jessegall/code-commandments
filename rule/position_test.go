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
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": ` + query + `}`

		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", query, err, reason)
		}
	}
}

const till = `<?php

namespace App;

class Till
{
    public function settled(array $lines): int
    {
        $total = count($lines);

        return $total;
    }

    public function wrapped(array $lines): int
    {
        if ($lines === []) {
            return 0;
        }

        foreach ($lines as $line) {
            return $line;
        }
    }
}
`

func TestARuleStepsOntoAChildRatherThanAnyDescendant(t *testing.T) {
	built := phpCodebase(t, "Till.php", till)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"child": {"is": "return"}}]}`:                    "[7]",
		`{"select": "function", "where": [{"descendant": {"is": "return"}}]}`:               "[7 14]",
		`{"select": "function", "where": [{"child": {"is": "return", "field": "stmts"}}]}`:  "[7]",
		`{"select": "function", "where": [{"child": {"is": "return", "field": "params"}}]}`: "[]",
		`{"select": "branch", "where": [{"child": {"is": "return"}}]}`:                      "[16]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestTheChildStepAnswersForASecondLanguage(t *testing.T) {
	module := pythontest.FromSource(t, map[string]string{"till.py": `def settled(total: int) -> int:
    return total


def wrapped(lines: list) -> int:
    for line in lines:
        return line
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"child": {"is": "return"}}]}`:      "[1]",
		`{"select": "function", "where": [{"descendant": {"is": "return"}}]}`: "[1 5]",
	} {
		if got := found(t, "python", query, module); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}
}

const checkout = `<?php

namespace App;

class Checkout
{
    public function __construct(private Queue $queue)
    {
    }

    public function settle(Order $order): void
    {
        $order->save();
    }

    public function later(Order $order): void
    {
        $this->queue->push(function () use ($order) {
            $order->save();
        });
    }
}
`

func TestADescendantStepStopsAtAFunctionNestedInside(t *testing.T) {
	built := phpCodebase(t, "Checkout.php", checkout)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"name": "settle"}, {"descendant": {"name": "save", "within": "ownBody"}}]}`: "[11]",
		`{"select": "function", "where": [{"name": "settle"}, {"descendant": {"name": "save"}}]}`:                      "[11]",
		`{"select": "function", "where": [{"name": "later"}, {"descendant": {"name": "save", "within": "ownBody"}}]}`:  "[]",
		`{"select": "function", "where": [{"name": "later"}, {"descendant": {"name": "save"}}]}`:                       "[16]",
	} {
		if got := found(t, "backend", query, built); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestOwnBodyAnswersForASecondLanguage(t *testing.T) {
	module := pythontest.FromSource(t, map[string]string{"checkout.py": `class Checkout:
    def __init__(self, queue):
        self.queue = queue

    def settle(self, order):
        order.save()

    def later(self, order):
        def finish():
            order.save()

        self.queue.push(finish)
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"name": "settle"}, {"descendant": {"name": "save", "within": "ownBody"}}]}`: "[5]",
		`{"select": "function", "where": [{"name": "later"}, {"descendant": {"name": "save", "within": "ownBody"}}]}`:  "[]",
		`{"select": "function", "where": [{"name": "later"}, {"descendant": {"name": "save"}}]}`:                       "[8]",
	} {
		if got := found(t, "python", query, module); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}
}

func TestAWithinTheToolCannotReadSaysWhy(t *testing.T) {
	query := `{"select": "function", "where": [{"descendant": {"name": "save", "within": "ownBodyOnly"}}]}`
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": ` + query + `}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "ownBody") {
		t.Errorf("a within of ownBodyOnly: %v, want the one value it takes", err)
	}
}
