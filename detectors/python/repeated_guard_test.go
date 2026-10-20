package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestTwoTypesAskingOfThemselvesRepeatNothing holds the rule to whose question a guard is. The journal's viewer
// protocol row and its migration reader each ask whether a row is neither completed nor deleted, of their own
// state, in two layers that may not import each other; naming that once would couple them, and each type
// answering for itself is what the rule asks for (#689). The same question asked twice about ONE type is still
// one question written twice.
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

// TestOneTypeAskedTwiceIsARepeatedGuard holds the other side: the same question about one object, asked from two
// places, is the question that object should answer.
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
