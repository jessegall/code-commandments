package python_test

import (
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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

func TestAFrozenValueObjectClearingAFieldThroughAMoverIsNotPhantom(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/task.py": `from dataclasses import dataclass, replace


@dataclass(frozen=True)
class Task:
    name: str
    closed_at: str | None = None

    def closed(self, timestamp: str) -> "Task":
        return self.moved(closed_at=timestamp)

    def reopened(self) -> "Task":
        return self.moved(closed_at=None)

    def moved(self, **fields) -> "Task":
        return replace(self, **fields)
`,
		"shop/report.py": `from shop.task import Task


def row(task: Task) -> dict:
    return {"name": task.name, "closed": task.closed_at.upper()}
`,
	})

	for _, found := range (pydetectors.PhantomNullableDetector{}).Find(codebase) {
		t.Errorf("a field the class clears through its own mover is reported as phantom at %s", found.Location())
	}
}

func TestAFieldReadOnlyBehindAnIsinstanceCheckIsNotPhantom(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"rows/__init__.py": "",
		"rows/cached.py": `from dataclasses import dataclass


class Model:
    version: int = 0


@dataclass(frozen=True)
class CachedRow:
    row: Model | None
    generation: int

    @property
    def version(self) -> int | None:
        return self.row.version if isinstance(self.row, Model) else None
`,
		"rows/cache.py": `from rows.cached import CachedRow, Model


def cached(rows: dict[int, Model], row_id: int) -> CachedRow:
    return CachedRow(rows.get(row_id), 1)
`,
	})

	for _, found := range (pydetectors.PhantomNullableDetector{}).Find(codebase) {
		t.Errorf("a field every read guards with isinstance is reported as phantom at %s", found.Location())
	}
}
