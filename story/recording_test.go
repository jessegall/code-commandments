package story

import (
	"slices"
	"testing"
)

const journaled = `2026-11-07T23:23:14.178916+00:00	699	edit	src/core/base/registry/setting_entry.py
2026-11-07T23:23:10.000000+00:00	699	edit	src/core/base/registry/setting_tree.py
2026-11-07T23:24:00.000000+00:00	707	sins	journal sins --changes
2026-11-07T23:25:00.000000+00:00	699	read	README.md
not a line of the journal
2026-11-07T23:26:00+00:00	707	edit	src/viewer/src/Cart.vue`

func TestARecordingHoldsEachWorkersEditsAndJudgingsInTheOrderTheJournalSawThem(t *testing.T) {
	var moves []string
	for _, move := range Recorded("/p", journaled).Moves {
		moves = append(moves, move.Worker+" "+move.Edits+map[bool]string{true: "judges"}[move.Judges])
	}

	want := []string{
		"worker-699 src/core/base/registry/setting_tree.py",
		"worker-699 src/core/base/registry/setting_entry.py",
		"worker-707 judges",
		"worker-707 src/viewer/src/Cart.vue",
	}

	if !slices.Equal(moves, want) {
		t.Errorf("the recording should hold the edits and the judging in time order, and nothing else the journal "+
			"saw:\n got %v\nwant %v", moves, want)
	}
}

func TestAnEmptyRecordHoldsNoMove(t *testing.T) {
	if moves := Recorded("/p", "").Moves; len(moves) != 0 {
		t.Errorf("an empty record holds no move: %v", moves)
	}
}
