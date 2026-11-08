package story

import (
	"slices"
	"testing"
	"time"
)

const ledgerEvents = `1000000000000	tree_reads_started	/p
1000000001000	judgings_told_the_tree_is_coming	/p/.journal/worktrees/development/worker-1
1000000002000	judgings_told_the_tree_is_coming	/p/.journal/worktrees/development/worker-1
1000000003000	judgings_answered_smaller	/p/.journal/worktrees/development/worker-1 because 1800 files differ
1000000004000	judgings_from_the_tree	/p/.journal/worktrees/development/worker-2
1000000060000	judgings_told_the_tree_is_coming	/p/.journal/worktrees/development/worker-1
1000000120000	judgings_told_the_tree_is_coming	/p/.journal/worktrees/development/worker-1
1000000121000	judgings_declined	/p/.journal/worktrees/development/worker-1 because no tree`

func TestARecordingCountsOneJudgingPerAskNotPerPoll(t *testing.T) {
	recorded := Recorded("/p", ledgerEvents, nil)

	var asks []string
	for _, move := range recorded.Moves {
		asks = append(asks, move.Worker+"@"+move.At.Format("05"))
	}

	want := []string{"worker-1@41", "worker-2@44", "worker-1@40", "worker-1@40"}
	if !slices.Equal(asks, want) {
		t.Fatalf("the ledger held four asks (a polled one answered smaller, one from the tree, and two a minute apart), "+
			"but the recording holds %v", asks)
	}
}

func TestARecordingPlacesEachWorkersEditsAmongItsJudgingsAndLeavesOutWhatFellOutsideTheStretch(t *testing.T) {
	commits := map[string]string{
		"worker-1": "@1000000002\nsrc/order.py\n\nsrc/viewer/Cart.vue\n@999999000\nsrc/before.py\n@1000999999\nsrc/after.py\n",
	}

	recorded := Recorded("/p", ledgerEvents, commits)

	var edits []string
	for _, move := range recorded.Moves {
		if move.Edits != "" {
			edits = append(edits, move.Edits)
		}
	}

	if len(edits) != 2 || edits[0] != "src/order.py" || edits[1] != "src/viewer/Cart.vue" {
		t.Fatalf("the recording should hold the two files edited inside the stretch, and nothing from before or after it: %v", edits)
	}

	for at, move := range recorded.Moves[1:] {
		if move.At.Before(recorded.Moves[at].At) {
			t.Fatalf("the moves are out of order at %d: %v", at+1, recorded.Moves)
		}
	}

	if !recorded.Moves[1].At.Equal(time.Unix(1000000002, 0).UTC()) || recorded.Moves[1].Edits == "" {
		t.Errorf("the edit at second 2 should come after worker-1's first ask at second 1: %v", recorded.Moves)
	}
}

func TestAnEmptyLedgerRecordsNothing(t *testing.T) {
	if moves := Recorded("/p", "", map[string]string{"worker-1": "@1\na.py\n"}).Moves; len(moves) != 0 {
		t.Errorf("with no judging to frame the stretch, nothing is recorded: %v", moves)
	}
}
