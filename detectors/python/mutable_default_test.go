package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAContainerDefaultIsFoundAndAnImmutableOneIsNot holds the rule to the fault it names: a container written as
// a default is made once when the def is defined, so one call's data turns up in the next. An immutable default
// is the fix and must pass, as must a container built inside the body.
func TestAContainerDefaultIsFoundAndAnImmutableOneIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a list written out":      {"def add(item: str, into: list[str] = []) -> list[str]:\n    into.append(item)\n    return into\n", 1},
		"a dict written out":      {"def tally(key: str, counts: dict[str, int] = {}) -> dict[str, int]:\n    counts[key] = 1\n    return counts\n", 1},
		"a set built by a call":   {"def seen(mark: str, marks: set[str] = set()) -> set[str]:\n    marks.add(mark)\n    return marks\n", 1},
		"a comprehension":         {"def rows(at: int, cells: list[int] = [n for n in range(3)]) -> list[int]:\n    cells.append(at)\n    return cells\n", 1},
		"two of them in one def":  {"def pair(a: list[int] = [], b: dict[str, int] = {}) -> None:\n    a.append(1)\n    b['x'] = 1\n", 2},
		"none as the default":     {"def add(item: str, into: list[str] | None = None) -> list[str]:\n    into = into if into is not None else []\n    into.append(item)\n    return into\n", 0},
		"a tuple as the default":  {"def at(point: tuple[int, int] = (0, 0)) -> int:\n    return point[0]\n", 0},
		"a string and a number":   {"def label(text: str = 'none', times: int = 1) -> str:\n    return text * times\n", 0},
		"a container in the body": {"def add(item: str) -> list[str]:\n    into: list[str] = []\n    into.append(item)\n    return into\n", 0},
		"a protocol's own method": {"from typing import Protocol\n\n\nclass Sink(Protocol):\n    def add(self, item: str, into: list[str] = []) -> None:\n        ...\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/thing.py": test.source})
			found := (python.MutableDefaultDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
