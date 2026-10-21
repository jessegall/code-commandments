package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAChangeToTheWalkedCollectionIsFoundAndACopyIsNot holds the rule to the fault it names: a loop that appends
// to, removes from or deletes out of the collection it is walking reads a sequence that moved under it. Changing
// a different collection, or walking a copy of the one it changes, is how the work is done right and must pass.
func TestAChangeToTheWalkedCollectionIsFoundAndACopyIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"removing from the list it walks":   {"def drop(items: list[str]) -> None:\n    for item in items:\n        if item == 'x':\n            items.remove(item)\n", 1},
		"appending to the list it walks":    {"def grow(items: list[str]) -> None:\n    for item in items:\n        items.append(item + '!')\n", 1},
		"deleting an item it walks by index": {"def drop(items: list[str]) -> None:\n    for at, item in enumerate(items):\n        del items[at]\n", 1},
		"deleting a key of a mapping's view": {"def clear(counts: dict[str, int]) -> None:\n    for key in counts.keys():\n        del counts[key]\n", 1},
		"changing what it walks reversed":   {"def drop(items: list[str]) -> None:\n    for item in reversed(items):\n        items.remove(item)\n", 1},
		"walking a sorted copy":             {"def drop(items: list[str]) -> None:\n    for item in sorted(items):\n        items.remove(item)\n", 0},
		"deleting out of what it walks":     {"def drop(items: list[str]) -> None:\n    for item in items:\n        del items[0]\n", 1},
		"changing a field it walks":         {"class Desk:\n    def __init__(self) -> None:\n        self.notes: list[str] = []\n\n    def tidy(self) -> None:\n        for note in self.notes:\n            self.notes.remove(note)\n", 1},
		"popping a dict it walks":           {"def clear(counts: dict[str, int]) -> None:\n    for key in counts:\n        counts.pop(key)\n", 1},
		"discarding from a set it walks":    {"def clear(marks: set[str]) -> None:\n    for mark in marks:\n        marks.discard(mark)\n", 1},
		"changing it from an inner loop":    {"def drop(items: list[str], keys: list[str]) -> None:\n    for item in items:\n        for key in keys:\n            items.remove(key)\n", 1},
		"changing a different collection":   {"def copy(items: list[str], into: list[str]) -> None:\n    for item in items:\n        into.append(item)\n", 0},
		"walking a copy by slice":           {"def drop(items: list[str]) -> None:\n    for item in items[:]:\n        items.remove(item)\n", 0},
		"walking a copy by call":            {"def drop(items: list[str]) -> None:\n    for item in list(items):\n        items.remove(item)\n", 0},
		"changing it after the loop":        {"def drop(items: list[str]) -> None:\n    for item in items:\n        pass\n    items.clear()\n", 0},
		"changing a field of another":       {"class Desk:\n    def __init__(self, other: 'Desk') -> None:\n        self.notes: list[str] = []\n        self.other = other\n\n    def tidy(self) -> None:\n        for note in self.notes:\n            self.other.notes.remove(note)\n", 0},
		"removing then returning at once":   {"def take(items: list[str], wanted: str) -> bool:\n    for item in items:\n        if item == wanted:\n            items.remove(item)\n            return True\n    return False\n", 0},
		"removing then breaking at once":    {"def take(items: list[str], wanted: str) -> str:\n    taken = ''\n    for item in items:\n        if item == wanted:\n            items.remove(item)\n            taken = item\n            break\n    return taken\n", 0},
		"removing then breaking the inner":  {"def drop(items: list[str], keys: list[str]) -> None:\n    for item in items:\n        for key in keys:\n            items.remove(key)\n            break\n", 1},
		"removing then going on":            {"def drop(items: list[str], wanted: str) -> None:\n    for item in items:\n        if item == wanted:\n            items.remove(item)\n            continue\n", 1},
		"appending in the loop's own else":  {"def seed(items: list[str], wanted: str) -> None:\n    for item in items:\n        if item == wanted:\n            break\n    else:\n        items.append(wanted)\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/thing.py": test.source})
			found := (python.CollectionMutatedWhileIteratedDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
