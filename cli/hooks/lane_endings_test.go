package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestEveryKeyThatEndsAWorktreeClosesItsLane(t *testing.T) {
	for _, key := range []string{"remove", "deleted", "abandon", "fail"} {
		root, _ := workerProject(t)
		worktree := stressWorktreeIn(root, 20)

		cutInto(t, root, worktree, "feature/ending-"+key)
		wroteInto(t, filepath.Join(worktree, "src", "made.py"), olderSin)

		kept.CutAt(worktree, "aaaaaaa")
		kept.Over(worktree)

		if !laneHeldFor(worktree) {
			t.Fatalf("%s: no lane was open, so closing it proves nothing", key)
		}

		if !kept.DropsLane(worktree) {
			t.Errorf("the key %s let go of no lane", key)
		}

		if laneHeldFor(worktree) {
			t.Errorf("the key %s left the lane of %s open", key, worktree)
		}
	}
}

func TestAFolderThatWentWithNoKeyAtAllIsCaughtByTheSweep(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 21)

	cutInto(t, root, worktree, "feature/gone-quietly")
	wroteInto(t, filepath.Join(worktree, "src", "made.py"), olderSin)

	kept.CutAt(worktree, "aaaaaaa")
	kept.Over(worktree)

	if !laneHeldFor(worktree) {
		t.Fatal("no lane was open before the folder went")
	}

	if err := os.RemoveAll(worktree); err != nil {
		t.Fatal(err)
	}

	kept.Overlaid()

	if laneHeldFor(worktree) {
		t.Error("the folder went with no key at all and its lane is still held after a sweep")
	}
}

func laneHeldFor(checkout string) bool {
	for _, lane := range kept.Lanes() {
		if lane.Checkout == checkout {
			return true
		}
	}

	return false
}

func TestALaneGoneByTheSweepTakesItsParsesWithIt(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 22)

	cutInto(t, root, worktree, "feature/sweep-parses")

	file := filepath.Join(worktree, "src", "made.py")
	wroteInto(t, file, olderSin)

	kept.CutAt(worktree, "aaaaaaa")
	kept.Over(worktree).Fills(time.Now(), "aaaaaaa")

	parsed.expect(file)

	if err := os.RemoveAll(worktree); err != nil {
		t.Fatal(err)
	}

	kept.Overlaid()

	parsed.mu.Lock()
	waiting := len(parsed.waiting)
	parsed.mu.Unlock()

	for _, named := range []string{file} {
		parsed.mu.Lock()
		_, held := parsed.held[named]
		parsed.mu.Unlock()

		if held {
			t.Errorf("the parse of %s is still held after its checkout went", named)
		}
	}

	if waiting > 0 {
		t.Errorf("%d file(s) of a checkout that is gone are still waiting to be parsed", waiting)
	}
}
