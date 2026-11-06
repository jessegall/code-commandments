package warm

import (
	"path/filepath"
	"testing"
	"time"
)

func TestAnOverlaySaysWhetherItHasEverBeenFilled(t *testing.T) {
	keeper := Keep()
	over := keeper.Over("/project/.journal/worktrees/main/worker-12")

	if state, _, _ := over.Filling(); state != NeverFilled {
		t.Errorf("a checkout nobody has read answers %v, and an empty overlay is read as a worker that changed nothing", state)
	}

	at := time.Now()
	over.Fills(at)

	state, when, why := over.Filling()
	if state != Filled || !when.Equal(at) || why != "" {
		t.Errorf("a filled overlay answers %v at %v because %q", state, when, why)
	}

	over.CouldNotFill("git could not list what the worktree differs by")

	state, _, why = over.Filling()
	if state != FillingFailed || why == "" {
		t.Errorf("a reading that failed answers %v because %q", state, why)
	}

	if over.Empty() != true {
		t.Error("the state is not the file count: an overlay with no files is still empty")
	}
}

func TestOnlyOneReadingOfACheckoutsOwnFilesRunsAtOnce(t *testing.T) {
	keeper := Keep()
	over := keeper.Over("/project/.journal/worktrees/main/worker-3")

	if !over.BeginsFilling() {
		t.Fatal("the first reading of a checkout's own files was refused its turn")
	}

	if over.BeginsFilling() {
		t.Error("a second reading started while the first was still running, so an answer could come from half a tree")
	}

	if state, _, _ := over.Filling(); state != BeingFilled {
		t.Errorf("an overlay being read answers %v", state)
	}

	over.CouldNotFill("the bridge died")

	if !over.BeginsFilling() {
		t.Error("a reading that failed left the overlay unable to try again")
	}
}

func TestAnOverlayOfACheckoutThatIsGoneIsLetGoWithWhatItHeld(t *testing.T) {
	keeper := Keep()

	var told []string
	keeper.Leaving(func(checkout string) { told = append(told, checkout) })

	here := t.TempDir()
	keeper.Over(here)
	gone := filepath.Join(t.TempDir(), "worker-9")
	keeper.Over(gone)

	if held := keeper.Overlaid(); held != 1 {
		t.Fatalf("after the sweep the keeper holds %d overlays, and only the checkout still on disk should be held", held)
	}

	if len(told) != 1 || told[0] != gone {
		t.Fatalf("the keeper let go of %v, and the checkout that is gone is %s", told, gone)
	}

	if _, _, why := keeper.Over(here).Filling(); why != "" {
		t.Errorf("the overlay that stayed was disturbed: %q", why)
	}
}
