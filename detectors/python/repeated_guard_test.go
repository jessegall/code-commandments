package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestTwoTypesAskingOfThemselvesRepeatNothing(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"viewer/row.py": `class V3Row:
    def __init__(self, completed: bool, deleted: bool) -> None:
        self.completed = completed
        self.deleted = deleted

    def is_live(self) -> bool:
        return not self.completed and not self.deleted
`,
		"migration/row.py": `class Version3Row:
    def __init__(self, completed: bool, deleted: bool) -> None:
        self.completed = completed
        self.deleted = deleted

    def is_live(self) -> bool:
        return not self.completed and not self.deleted
`,
	})

	found := (python.RepeatedGuardDetector{}).Find(codebase)
	if len(found) != 0 {
		t.Errorf("two types asking of themselves were read as one repeated guard: %v", found)
	}
}

func TestOneTypeAskedTwiceIsARepeatedGuard(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"shop/rows.py": `class Row:
    def __init__(self, completed: bool, deleted: bool) -> None:
        self.completed = completed
        self.deleted = deleted


def shown(row: Row) -> bool:
    return not row.completed and not row.deleted


def counted(row: Row) -> bool:
    return not row.completed and not row.deleted
`,
	})

	found := (python.RepeatedGuardDetector{}).Find(codebase)
	if len(found) != 2 {
		t.Errorf("one type's question asked twice found %d times, want 2: %v", len(found), found)
	}
}
