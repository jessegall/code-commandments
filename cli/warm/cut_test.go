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
	first.Fills(keeper.now(), "aaaaaaa")

	if state, _, _ := first.Filling(); state != Filled {
		t.Fatalf("the first worker's lane says it is %v rather than filled", state)
	}

	keeper.CutAt(folder, "bbbbbbb")

	second := keeper.Over(folder)
	if second == first {
		t.Fatal("a worker cut again into the same folder was handed the last worker's own overlay")
	}

	if state, _, _ := second.Filling(); state == Filled {
		t.Error("the second worker's lane says it is already filled, so it stands on what the first worker read")
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
	first.Fills(keeper.now(), "aaaaaaa")

	keeper.CutAt(folder, "aaaaaaa")

	if again := keeper.Over(folder); again != first {
		t.Fatal("a worker asking a second question from the same cut lost the overlay it had just filled")
	}

	if state, _, _ := keeper.Over(folder).Filling(); state != Filled {
		t.Error("the worker's lane is no longer filled, so its next answer pays the read again")
	}
}
