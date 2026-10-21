package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAContainerTheClassHoldsIsFoundAndAnInstancesOwnIsNot settles the sibling the mutable-default rule opens
// one level up: a container declared in the class body, which every instance then shares, changed in place by
// a method. An instance that assigns the name first has one of its own, a container nobody changes is a
// constant, and a plain value is no container at all.
func TestAContainerTheClassHoldsIsFoundAndAnInstancesOwnIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a list the class holds, appended to":   {"class Desk:\n    notes: list[str] = []\n\n    def pin(self, note: str) -> None:\n        self.notes.append(note)\n", 1},
		"a dict the class holds, written into":  {"class Desk:\n    counts: dict[str, int] = {}\n\n    def count(self, line: str) -> None:\n        self.counts.update({line: 1})\n", 1},
		"an instance's own, assigned in init":   {"class Desk:\n    notes: list[str] = []\n\n    def __init__(self) -> None:\n        self.notes = []\n\n    def pin(self, note: str) -> None:\n        self.notes.append(note)\n", 0},
		"a container nobody changes":            {"class Desk:\n    notes: list[str] = []\n\n    def waiting(self) -> int:\n        return len(self.notes)\n", 0},
		"a plain value the class holds":         {"class Desk:\n    limit: int = 3\n\n    def over(self, counted: int) -> bool:\n        return counted > self.limit\n", 0},
		"a container built in init, as it should be": {"class Desk:\n    def __init__(self) -> None:\n        self.notes: list[str] = []\n\n    def pin(self, note: str) -> None:\n        self.notes.append(note)\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/desk.py": test.source})
			found := (python.MutableStaticStateDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
