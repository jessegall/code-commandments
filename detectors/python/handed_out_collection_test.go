package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAHandedOutCollectionIsFoundAndAGuardedOneIsNot holds the rule to the fault it names: a getter whose whole
// body returns the class's own list, dict or set. A copy, a tuple, a Sequence the caller cannot change, and a
// getter that answers something else entirely are how a class gives its collection away safely and must pass.
func TestAHandedOutCollectionIsFoundAndAGuardedOneIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a list handed out whole":      {"class Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    def notes(self) -> list[str]:\n        return self._notes\n", 1},
		"a dict handed out whole":      {"class Desk:\n    def __init__(self) -> None:\n        self._counts: dict[str, int] = {}\n\n    def counts(self) -> dict[str, int]:\n        return self._counts\n", 1},
		"a property handing one out":   {"class Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    @property\n    def notes(self) -> list[str]:\n        return self._notes\n", 1},
		"a copy handed out":            {"class Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    def notes(self) -> list[str]:\n        return list(self._notes)\n", 0},
		"a tuple handed out":           {"class Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    def notes(self) -> tuple[str, ...]:\n        return tuple(self._notes)\n", 0},
		"a sequence they cannot change": {"from collections.abc import Sequence\n\n\nclass Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    def notes(self) -> Sequence[str]:\n        return self._notes\n", 0},
		"a count, not the collection":  {"class Desk:\n    def __init__(self) -> None:\n        self._notes: list[str] = []\n\n    def waiting(self) -> int:\n        return len(self._notes)\n", 0},
		"a collection it was handed":   {"class Desk:\n    def __init__(self, notes: list[str]) -> None:\n        self._notes = notes\n\n    def notes(self) -> list[str]:\n        return self._notes\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/desk.py": test.source})
			found := (python.HandedOutCollectionDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
