package python_test

import (
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestATestsReadIsNoAssumption holds a field that is genuinely None for some objects, handed on to another optional
// holder that is checked where it is used: a test that builds an object with the field set and indexes it assumes
// nothing about the code, while the same index in the code itself does.
func TestATestsReadIsNoAssumption(t *testing.T) {
	adopted := "from dataclasses import dataclass\n\n\n" +
		"@dataclass(frozen=True)\nclass Adopted:\n    pid: int\n    saved: list | None\n\n\n" +
		"class Supervisor:\n    def __init__(self, adopted: Adopted | None) -> None:\n        self.saved = adopted.saved if adopted else None\n"
	test := "from supervisor import Adopted\n\n\ndef test_saved() -> None:\n    adopted = Adopted(4, [1, 2])\n    assert adopted.saved[1] == 2\n"
	used := "from supervisor import Adopted\n\n\ndef first(adopted: Adopted) -> int:\n    return adopted.saved[0]\n"

	if found := (pydetectors.PhantomNullableDetector{}).Find(pythontest.FromSource(t, map[string]string{"supervisor.py": adopted, "test_supervisor.py": test})); len(found) != 0 {
		t.Errorf("a test's read of the field was counted as an assumption: %v", found[0].Location())
	}
	if found := (pydetectors.PhantomNullableDetector{}).Find(pythontest.FromSource(t, map[string]string{"supervisor.py": adopted, "reader.py": used})); len(found) == 0 {
		t.Error("the code indexing the field unguarded was not flagged")
	}
}
