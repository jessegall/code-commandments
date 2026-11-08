package story

import (
	"path/filepath"
	"slices"
	"strings"
	"time"
)

// Recording is a stretch of a real project's work as its journal recorded it: what each of its workers edited and when each ran a judging, in the order it happened. Nothing our plugin answered is in it, so a replay judges our plugin rather than repeating it.
type Recording struct {
	Project string `json:"project"`
	Moves   []Move `json:"moves"`
}

// Move is one thing one worker did at one moment: edited a file, named relative to its checkout, or asked for a judging.
type Move struct {
	At     time.Time `json:"at"`
	Worker string    `json:"worker"`
	Edits  string    `json:"edits,omitempty"`
	Judges bool      `json:"judges,omitempty"`
}

const (
	anEdit   = "edit"
	aJudging = "sins"
)

func Recorded(project, journaled string) Recording {
	var moves []Move

	for _, line := range strings.Split(journaled, "\n") {
		fields := strings.SplitN(strings.TrimRight(line, "\r"), "\t", 4)
		if len(fields) != 4 {
			continue
		}

		at, err := time.Parse(time.RFC3339Nano, fields[0])
		if err != nil {
			continue
		}

		worker := "worker-" + fields[1]

		switch fields[2] {
		case anEdit:
			moves = append(moves, Move{At: at.UTC(), Worker: worker, Edits: fields[3]})
		case aJudging:
			moves = append(moves, Move{At: at.UTC(), Worker: worker, Judges: true})
		}
	}

	slices.SortStableFunc(moves, func(a, b Move) int { return a.At.Compare(b.At) })

	return Recording{Project: project, Moves: moves}
}

var replayed = Python("a file a worker of a real project edited", "RECORDED = True\n")

func (s *Story) Replays(recording Recording) int {
	s.t.Helper()

	workers := map[string]*Agent{}
	judgings := 0

	for _, move := range recording.Moves {
		worker, cut := workers[move.Worker]
		if !cut {
			worker = s.Worker(move.Worker)
			workers[move.Worker] = worker
		}

		if move.Judges {
			s.AsksIn(worker.Folder(), "sins", "--changes")
			judgings++

			continue
		}

		worker.Edits(replayed, strings.TrimSuffix(move.Edits, filepath.Ext(move.Edits))+".py")
	}

	return judgings
}
