package hooks

import (
	"path/filepath"
	"testing"
)

func laneKeys(t *testing.T) (root, worktree string) {
	t.Helper()

	root, _ = workerProject(t)
	worktree = stressWorktreeIn(root, 19)

	cutInto(t, root, worktree, "feature/keys")

	wroteInto(t, filepath.Join(worktree, "src", "made.py"), olderSin)

	return root, worktree
}

func TestACutDeliveredTwiceOpensOneLane(t *testing.T) {
	_, worktree := laneKeys(t)

	kept.CutAt(worktree, "aaaaaaa")
	kept.Over(worktree)

	before := kept.Lanes()

	kept.CutAt(worktree, "aaaaaaa")
	kept.Over(worktree)

	if after := kept.Lanes(); len(after) != len(before) {
		t.Errorf("a cut delivered twice left %d lane(s) where the first left %d", len(after), len(before))
	}
}

func TestARemoveBeforeACutOpensNothing(t *testing.T) {
	_, worktree := laneKeys(t)

	if dropped := kept.DropsLane(worktree); dropped {
		t.Error("a remove for a lane nobody opened says it let one go")
	}

	for _, lane := range kept.Lanes() {
		if lane.Checkout == worktree {
			t.Errorf("a remove opened a lane of its own for %s", worktree)
		}
	}
}

func TestAnAdvanceForALaneNobodyOpenedIsAnswered(t *testing.T) {
	_, worktree := laneKeys(t)

	kept.CutAt(worktree, "bbbbbbb")

	if cut := kept.CutOf(worktree); cut != "" {
		t.Errorf("an advance for a lane nobody opened left a lane cut from %q", cut)
	}

	kept.Over(worktree)

	if cut := kept.CutOf(worktree); cut != "bbbbbbb" {
		t.Errorf("the lane opened by the first question after that advance says it was cut from %q", cut)
	}
}

func TestADeletedAfterARemoveChangesNothing(t *testing.T) {
	_, worktree := laneKeys(t)

	kept.CutAt(worktree, "aaaaaaa")
	kept.Over(worktree)

	if !kept.DropsLane(worktree) {
		t.Fatal("the remove let go of nothing, so the second key has nothing to be idempotent about")
	}

	before := len(kept.Lanes())

	if kept.DropsLane(worktree) {
		t.Error("a deleted after a remove says it let a second lane go")
	}

	if after := len(kept.Lanes()); after != before {
		t.Errorf("a deleted after a remove left %d lane(s) where the remove left %d", after, before)
	}
}

func TestEveryKeyIsAnsweredTwiceAndInReverseWithOneOutcome(t *testing.T) {
	_, worktree := laneKeys(t)

	for _, order := range [][]string{
		{"cut", "cut", "advance", "advance", "remove", "remove", "deleted"},
		{"deleted", "remove", "advance", "cut", "cut", "advance", "remove"},
	} {
		kept.DropsLane(worktree)

		for _, key := range order {
			switch key {
			case "cut":
				kept.CutAt(worktree, "aaaaaaa")
				kept.Over(worktree)
			case "advance":
				kept.CutAt(worktree, "bbbbbbb")
			case "remove", "deleted":
				kept.DropsLane(worktree)
			}
		}

		for _, lane := range kept.Lanes() {
			if lane.Checkout == worktree {
				t.Errorf("after the order %v a lane of %s is still held, and the last key let it go", order, worktree)
			}
		}
	}
}
