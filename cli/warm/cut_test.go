package warm

import (
	"testing"
)

func TestAWorkerCutAgainIntoTheSameFolderMeetsNothingOfTheLast(t *testing.T) {
	keeper := Keep()

	var told []string
	keeper.Leaving(func(checkout string) { told = append(told, checkout) })

	folder := t.TempDir()

	keeper.CutAt(folder, "aaaaaaa")
	first := keeper.Over(folder)
	first.Patch(nil, map[string]Known{"src/made.py": {}})

	if held := first.Count(); held != 1 {
		t.Fatalf("the first worker's overlay holds %d of its files, and it wrote 1", held)
	}

	keeper.CutAt(folder, "bbbbbbb")

	second := keeper.Over(folder)
	if second == first {
		t.Fatal("a worker cut again into the same folder was handed the last worker's own overlay")
	}

	if held := second.Count(); held != 0 {
		t.Errorf("the second worker's first answer carries %d of the first worker's files", held)
	}

	if len(told) != 1 || told[0] != folder {
		t.Errorf("the parses of the released worker were not let go: told %v", told)
	}

	if cut := keeper.CutOf(folder); cut != "bbbbbbb" {
		t.Errorf("the lane says it was cut from %q", cut)
	}
}

func TestTheSameWorkerAskingAgainKeepsItsOwnOverlay(t *testing.T) {
	keeper := Keep()
	folder := t.TempDir()

	keeper.CutAt(folder, "aaaaaaa")
	first := keeper.Over(folder)
	first.Patch(nil, map[string]Known{"src/made.py": {}})

	keeper.CutAt(folder, "aaaaaaa")

	if again := keeper.Over(folder); again != first {
		t.Fatal("a worker asking a second question from the same cut lost the overlay it had just filled")
	}

	if held := keeper.Over(folder).Count(); held != 1 {
		t.Errorf("the worker's own file is no longer held: %d", held)
	}
}
