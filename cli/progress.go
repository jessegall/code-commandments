package cli

import (
	"fmt"
	"io"
	"math"
	"os"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const barWidth = 24

// Progress is a carriage-return bar drawn on stderr, so it never mixes into findings or a checklist on stdout.
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

const JournalReport = "JOURNAL_REPORT"

const PlainProgress = "COMMANDMENTS_PLAIN_PROGRESS"

const Timing = "COMMANDMENTS_TIMING"

func Timed() bool {
	return os.Getenv(Timing) != ""
}

func SayWork(console Console, work engine.Work) {
	SayWorkWhen(false, console, work)
}

func SayWorkWhen(asked bool, console Console, work engine.Work) {
	if !asked && !Timed() {
		return
	}

	fmt.Fprintf(console.Err, "· work: %d nodes, %d checks, %d analyses\n", work.Nodes, work.Checks, work.Analyses)
}

func Watched() bool {
	return os.Getenv(JournalReport) != "" || os.Getenv(PlainProgress) != ""
}

// Relayed names a run whose lines a judge in a terminal passes on: its progress comes as plain lines that judge draws
// as a bar, and everything else as a terminal shows it, colour and layout included.
const Relayed = "COMMANDMENTS_RELAYED"

func IsRelayed() bool {
	return os.Getenv(Relayed) != ""
}

// ProgressRelay is the error stream of a judging done for another run: what the judging says goes to the stream it holds, and its progress lines go to that run as they are made.
type ProgressRelay struct {
	io.Writer
	Send func(string)
}

type progressLines func(string)

func (send progressLines) Write(line []byte) (int, error) {
	send(strings.TrimRight(string(line), "\n"))

	return len(line), nil
}

func NewProgress(stream io.Writer) *Progress {
	if relay, relayed := stream.(ProgressRelay); relayed {
		return &Progress{stream: progressLines(relay.Send), enabled: true, plain: true, total: 1, lastFilled: -1, lastShare: -1}
	}

	terminal := isTerminal(stream)
	plain := !terminal && (Watched() || IsRelayed())

	return &Progress{stream: stream, enabled: terminal || plain, plain: plain, total: 1, lastFilled: -1, lastShare: -1}
}

func (p *Progress) Status(message string) {
	if !p.enabled || p.plain {
		return
	}

	fmt.Fprintf(p.stream, "\r\033[2K\033[2m%s\033[0m", message)
}

func (p *Progress) Plan(steps int) {
	if !p.enabled {
		return
	}

	if !p.planned {
		p.planned, p.total = true, 0
	}

	p.total, p.active = max(1, p.total+max(0, steps)), true
}

func (p *Progress) Restart(steps int) {
	if !p.enabled {
		return
	}

	p.total, p.current, p.planned, p.active = max(1, steps), 0, steps > 0, true
	p.lastFilled, p.lastShare = -1, -1
}

func (p *Progress) Start() {
	p.reach(p.current, "judging", "")
}

func (p *Progress) Advance(label string) {
	if !p.active {
		return
	}

	p.reach(p.current+1, "judging", label)
}

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

// Live is a bar drawn in place on a terminal, not written as lines.
func (p *Progress) Live() bool {
	return p.enabled && !p.plain
}

// At draws the bar where another run's plain progress line said it stands.
func (p *Progress) At(phase string, done, total int) {
	if !p.enabled {
		return
	}

	p.total, p.active = max(1, total), true
	p.current = min(p.total, max(0, done))
	p.render(phase, "")
}

// PlainProgressOf reads a line a plain progress wrote back into its phase and count.
func PlainProgressOf(line string) (phase string, done, total int, read bool) {
	word, count, spaced := strings.Cut(strings.TrimSpace(line), " ")
	if !spaced {
		return "", 0, 0, false
	}

	if _, err := fmt.Sscanf(count, "%d/%d", &done, &total); err != nil || fmt.Sprintf("%d/%d", done, total) != count {
		return "", 0, 0, false
	}

	if word == counting("judging") {
		word = "judging"
	}

	return word, done, total, true
}

func isTerminal(stream io.Writer) bool {
	file, isFile := stream.(*os.File)

	if !isFile {
		return false
	}

	info, err := file.Stat()

	return err == nil && info.Mode()&os.ModeCharDevice != 0
}

func counting(phase string) string {
	if phase == "judging" {
		return "detector"
	}

	return phase
}
