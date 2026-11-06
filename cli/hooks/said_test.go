package hooks

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAShortfallIsSaidOncePerFileAndAgainWhenItChanges(t *testing.T) {
	space := workspace.At(t.TempDir(), "")
	said := SaidIn(space)

	first := said.Fresh(map[string]Narrowed{"a.py": noTreeHeld, "b.py": noTreeHeld})
	if len(first) != 2 {
		t.Fatalf("the first edit says %d of 2 shortfalls", len(first))
	}

	for range 10 {
		if again := said.Fresh(map[string]Narrowed{"a.py": noTreeHeld, "b.py": noTreeHeld}); len(again) != 0 {
			t.Fatalf("the same shortfall is said again: %v", again)
		}
	}

	changed := said.Fresh(map[string]Narrowed{"a.py": parseFailed, "b.py": noTreeHeld})
	if len(changed) != 1 || changed["a.py"] != parseFailed {
		t.Errorf("a file whose reason changed says %v", changed)
	}

	if whole := said.Fresh(map[string]Narrowed{"a.py": ""}); len(whole) != 0 {
		t.Errorf("a whole judging says %v", whole)
	}

	if after := said.Fresh(map[string]Narrowed{"a.py": parseFailed}); len(after) != 1 {
		t.Errorf("a file judged whole and then narrowed again says %v, and the reader was never told the second time", after)
	}
}
