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
// stdout. It is silent unless its stream is an interactive terminal: piped runs, hooks, CI and tests see
// nothing.
type Progress struct {
	stream     io.Writer
	enabled    bool
	total      int
	current    int
	lastFilled int
	active     bool
}

// NewProgress draws on stream, and only when stream is a terminal.
func NewProgress(stream io.Writer) *Progress {
	return &Progress{stream: stream, enabled: isTerminal(stream), total: 1, lastFilled: -1}
}

// Status shows an opaque line for a phase whose progress cannot be counted, overwritten by the next one.
func (p *Progress) Status(message string) {
	if !p.enabled {
		return
	}

	fmt.Fprintf(p.stream, "\r\033[2K\033[2m%s\033[0m", message)
}

// Start begins a bar of total steps.
func (p *Progress) Start(total int) {
	if !p.enabled {
		return
	}

	p.total, p.current, p.lastFilled, p.active = max(1, total), 0, -1, true
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

	fmt.Fprint(p.stream, "\r\033[2K")
	p.active = false
}

func (p *Progress) filled() int {
	return int(math.Round(float64(barWidth*p.current) / float64(p.total)))
}

func (p *Progress) render(phase, label string) {
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
