package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAFilledParameterIsFoundAndACommandIsNot holds the rule to the fault it names: a def returning None that
// writes an attribute of what it was handed, whose caller reads that attribute back off. A command the caller
// never looks at again, a def that returns its answer, and one handed a value it only reads are how the work is
// done right and must pass.
func TestAFilledParameterIsFoundAndACommandIsNot(t *testing.T) {
	const slip = "class Slip:\n    def __init__(self) -> None:\n        self.net = 0\n        self.sealed = False\n\n\n"
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"filled, then read back": {slip + "class Pricer:\n    def price(self, lines: list[int]) -> int:\n        slip = Slip()\n        self.add_lines(slip, lines)\n\n        return slip.net\n\n    def add_lines(self, slip: Slip, lines: list[int]) -> None:\n        slip.net = sum(lines)\n", 1},
		"a command nobody reads back": {slip + "class Pricer:\n    def close(self, slip: Slip) -> None:\n        self.seal(slip)\n\n    def seal(self, slip: Slip) -> None:\n        slip.sealed = True\n", 0},
		"the answer returned": {slip + "class Pricer:\n    def price(self, lines: list[int]) -> int:\n        slip = Slip()\n        slip.net = self.net_of(lines)\n\n        return slip.net\n\n    def net_of(self, lines: list[int]) -> int:\n        return sum(lines)\n", 0},
		"a value only read": {slip + "class Pricer:\n    def price(self, lines: list[int]) -> int:\n        slip = Slip()\n        self.report(slip)\n\n        return slip.net\n\n    def report(self, slip: Slip) -> None:\n        print(slip.net)\n", 0},
		"filled with no caller at all": {slip + "class Pricer:\n    def add_lines(self, slip: Slip, lines: list[int]) -> None:\n        slip.net = sum(lines)\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/pricing.py": test.source})
			found := (python.FilledParameterDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
