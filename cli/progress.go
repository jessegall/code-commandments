package cli

import (
	"fmt"
	"io"
	"math"
	"os"
	"strings"
)

// barWidth is how many cells the bar has.
const barWidth = 24

// Progress is a carriage-return bar drawn on stderr, so it never mixes into findings or a checklist on
// stdout. It is silent unless its stream is an interactive terminal, or a journal check reads the run: there it
// writes a plain `reading 120/983` or `detector 37/257` line each time the share done moves, which the check reads as
// its progress.
// Other piped runs, hooks, CI and tests see nothing.
type Progress struct {
	stream     io.Writer
	enabled    bool
	plain      bool
	total      int
	current    int
	lastFilled int
	lastShare  int
	lastWord   string
	active     bool
	planned    bool
}

// JournalReport names the variable the journal sets for a check it runs, its report's file.
const JournalReport = "JOURNAL_REPORT"

// PlainProgress names the variable that asks for the counted lines where nothing says a journal is watching: a
// journal command run through a reader of ours reads them, and a terminal is not what it has.
const PlainProgress = "COMMANDMENTS_PLAIN_PROGRESS"

// Watched says whether a journal is reading this run: a check that named its report, or a command that asked for
// the counted lines. What it reads is a card in someone's chat rather than a terminal, so a run it watches counts
// in plain lines and says what it found in words.
func Watched() bool {
	return os.Getenv(JournalReport) != "" || os.Getenv(PlainProgress) != ""
}

// NewProgress draws on stream when it is a terminal, and writes plain lines when a journal check or a journal
// command reads it.
func NewProgress(stream io.Writer) *Progress {
	terminal := isTerminal(stream)
	plain := !terminal && Watched()

	return &Progress{stream: stream, enabled: terminal || plain, plain: plain, total: 1, lastFilled: -1, lastShare: -1}
}

// Status shows an opaque line for a phase whose progress cannot be counted, overwritten by the next one.
func (p *Progress) Status(message string) {
	if !p.enabled || p.plain {
		return
	}

	fmt.Fprintf(p.stream, "\r\033[2K\033[2m%s\033[0m", message)
}

// Plan adds steps to what the run has to get through: every file a phase will read and every detector it will
// run, each counted into ONE grand total. A run judges one engine after another, so the total grows as each
// part is reached, and the count never starts again: a bar fills once over the whole run.
func (p *Progress) Plan(steps int) {
	if !p.enabled {
		return
	}

	if !p.planned {
		p.planned, p.total = true, 0
	}

	p.total, p.active = max(1, p.total+max(0, steps)), true
}

// Start says the detectors are the part now running. What they will take was planned before the run began, so
// this counts nothing again: it draws where the count stands, which is what moves a journal's bar off the
// reading phase at once.
func (p *Progress) Start() {
	p.reach(p.current, "judging", "")
}

// Advance moves one step, labelled with what now runs.
func (p *Progress) Advance(label string) {
	if !p.active {
		return
	}

	p.reach(p.current+1, "judging", label)
}

// Phase is this bar as a (done, total) reporter for one named phase, for a job that should not know whether
// anyone is watching. The job counts its own steps from zero while the bar counts the whole run, so a report is
// read from where the count stood when the job began; a job that starts over, as the next engine's reading does,
// carries on from there rather than dropping back. What the job will take is planned by whoever starts it, so
// nothing is counted into the total twice.
func (p *Progress) Phase(phase string) func(done, total int) {
	base, last, started := 0, 0, false

	return func(done, _ int) {
		if !started || done < last {
			base, started = p.current, true
		}

		last = done
		p.reach(base+done, phase, "")
	}
}

// reach moves the count to at, never backwards and never past the total, and redraws when what is drawn moves.
func (p *Progress) reach(at int, phase, label string) {
	if !p.enabled {
		return
	}

	p.active = true
	p.current = min(p.total, max(p.current, at))

	if p.plain {
		p.render(phase, label)

		return
	}

	if filled := p.filled(); filled != p.lastFilled || p.current == p.total {
		p.lastFilled = filled
		p.render(phase, label)
	}
}

// Finish clears the bar line; safe when nothing is drawn.
func (p *Progress) Finish() {
	if !p.active {
		return
	}

	if !p.plain {
		fmt.Fprint(p.stream, "\r\033[2K")
	}
	p.active = false
}

func (p *Progress) filled() int {
	return int(math.Round(float64(barWidth*p.current) / float64(p.total)))
}

func (p *Progress) render(phase, label string) {
	if p.plain {
		if share := p.current * 100 / p.total; share != p.lastShare || counting(phase) != p.lastWord {
			p.lastShare, p.lastWord = share, counting(phase)
			fmt.Fprintf(p.stream, "%s %d/%d\n", counting(phase), p.current, p.total)
		}

		return
	}
	filled := p.filled()
	bar := strings.Repeat("█", filled) + strings.Repeat("░", barWidth-filled)

	fmt.Fprintf(p.stream, "\r\033[2K\033[2m%s\033[0m [%s] %d/%d \033[2m%s\033[0m", phase, bar, p.current, p.total, label)
}

func isTerminal(stream io.Writer) bool {
	file, isFile := stream.(*os.File)

	if !isFile {
		return false
	}

	info, err := file.Stat()

	return err == nil && info.Mode()&os.ModeCharDevice != 0
}

// counting is the word a journal check's line counts in: judging is one detector a step, so it says detector.
func counting(phase string) string {
	if phase == "judging" {
		return "detector"
	}

	return phase
}
