package story

import (
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
)

// Recording is a stretch of a real project's work as our plugin saw it: what each of its workers edited and when each asked for a judging, in the order it happened, read from that project's ledger and from its workers' commits.
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

const aNewAsk = 5 * time.Second

var answers = []string{ledger.JudgingsAnsweredSmaller, ledger.JudgingsDeclined, ledger.JudgingsFromTheTree}

func Recorded(project, events string, commits map[string]string) Recording {
	judged := judgingsIn(events)
	if len(judged) == 0 {
		return Recording{Project: project}
	}

	began, ended := judged[0].At, judged[len(judged)-1].At

	moves := judged
	for worker, log := range commits {
		for _, edit := range editsIn(worker, log) {
			if edit.At.Before(began) || edit.At.After(ended) {
				continue
			}

			moves = append(moves, edit)
		}
	}

	slices.SortStableFunc(moves, func(a, b Move) int { return a.At.Compare(b.At) })

	return Recording{Project: project, Moves: moves}
}

func judgingsIn(events string) []Move {
	var moves []Move

	last := map[string]time.Time{}
	answered := map[string]bool{}

	for _, line := range strings.Split(events, "\n") {
		fields := strings.SplitN(line, "\t", 3)
		if len(fields) != 3 {
			continue
		}

		millis, err := strconv.ParseInt(fields[0], 10, 64)
		if err != nil {
			continue
		}

		named := fields[1]
		if named != ledger.JudgingsToldTheTreeIsComing && !slices.Contains(answers, named) {
			continue
		}

		at := time.UnixMilli(millis).UTC()
		where, _, _ := strings.Cut(fields[2], " because ")
		worker := filepath.Base(where)

		if answered[worker] || at.Sub(last[worker]) > aNewAsk {
			moves = append(moves, Move{At: at, Worker: worker, Judges: true})
		}

		last[worker] = at
		answered[worker] = slices.Contains(answers, named)
	}

	return moves
}

func editsIn(worker, log string) []Move {
	var moves []Move

	var at time.Time

	for _, line := range strings.Split(log, "\n") {
		line = strings.TrimSpace(line)

		if stamp, isCommit := strings.CutPrefix(line, "@"); isCommit {
			seconds, err := strconv.ParseInt(stamp, 10, 64)
			if err == nil {
				at = time.Unix(seconds, 0).UTC()
			}

			continue
		}

		if line == "" || at.IsZero() {
			continue
		}

		moves = append(moves, Move{At: at, Worker: worker, Edits: line})
	}

	return moves
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
