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
	before     int
	lastFilled int
	lastShare  int
	active     bool
}

// journalCheck names the variable the journal sets for a check it runs, its report's file.
const journalCheck = "JOURNAL_REPORT"

// NewProgress draws on stream when it is a terminal, and writes plain lines when a journal check reads it.
func NewProgress(stream io.Writer) *Progress {
	terminal := isTerminal(stream)
	plain := !terminal && os.Getenv(journalCheck) != ""

	return &Progress{stream: stream, enabled: terminal || plain, plain: plain, total: 1, lastFilled: -1, lastShare: -1}
}

// Status shows an opaque line for a phase whose progress cannot be counted, overwritten by the next one.
func (p *Progress) Status(message string) {
	if !p.enabled || p.plain {
		return
	}

	fmt.Fprintf(p.stream, "\r\033[2K\033[2m%s\033[0m", message)
}

// Expect counts the steps that come before the bar starts, tracked through Phase, into the bar Start draws: the
// files a run reads before it judges.
func (p *Progress) Expect(steps int) {
	p.before = steps
}

// Start begins a bar of total steps, after the steps Expect counted; a journal check's bar counts the total alone.
func (p *Progress) Start(total int) {
	if !p.enabled {
		return
	}

	if p.plain {
		p.total, p.current, p.lastShare, p.active = max(1, total), 0, -1, true
		p.render("judging", "")

		return
	}

	p.total, p.current, p.lastFilled, p.active = max(1, p.before+total), min(p.current, p.before), -1, true
	p.render("judging", "")
}

// Advance moves one step, labelled with what now runs.
func (p *Progress) Advance(label string) {
	if !p.active {
		return
	}

	p.current = min(p.total, p.current+1)
	p.render("judging", label)
}

// Phase is this bar as a (done, total) reporter for one named phase, for a job that should not know
// whether anyone is watching.
func (p *Progress) Phase(phase string) func(done, total int) {
	return func(done, total int) {
		p.Track(done, total, phase)
	}
}

// Track draws the bar from a (done, total) pair, redrawing only when the filled part moves.
func (p *Progress) Track(done, total int, phase string) {
	if !p.enabled {
		return
	}

	p.active = true
	p.total = max(1, total)
	p.current = min(p.total, max(0, done))

	if filled := p.filled(); filled != p.lastFilled || p.current == p.total {
		p.lastFilled = filled
		p.render(phase, "")
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
		if share := p.current * 100 / p.total; share != p.lastShare {
			p.lastShare = share
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
