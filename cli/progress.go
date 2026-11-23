package cli

import (
	"fmt"
	"io"
	"math"
	"os"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/engine"
)

const barWidth = 24

var spinnerFrames = [...]string{"⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"}

// Progress is a carriage-return bar drawn on stderr, so it never mixes into findings or a checklist on stdout. On a
// terminal it opens on a spinner that a clock keeps turning from the first word to the last, whatever the work reports.
type Progress struct {
	drawing    sync.Mutex
	stream     io.Writer
	clock      clock
	ended      chan struct{}
	stopped    chan struct{}
	turns      int
	phase      string
	label      string
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

// SayJudged is the line every judging ends on, whatever started it: how many files it read and how many of them the
// cache served, how long reading and judging took, how many rule answers came from memory, and how many sins it found.
func SayJudged(w io.Writer, reading, judging time.Duration, sins int, work engine.Work) {
	fmt.Fprintf(w, "· read %d files in %.1fs (%d from the cache, %d by the bridges), judged in %.1fs (%d rule answers from memory, %d asked): %d sins\n",
		work.Parsed, reading.Seconds(), work.Kept, work.Parsed-work.Kept, judging.Seconds(), work.Remembered, work.Judged, sins)
}

func SayWork(console Console, work engine.Work) {
	SayWorkWhen(false, console, work)
}

func SayWorkWhen(asked bool, console Console, work engine.Work) {
	if !asked && !Timed() {
		return
	}

	fmt.Fprintf(console.Err, "· work: %d nodes, %d checks, %d analyses, %.1f times over one file at most\n", work.Nodes, work.Checks, work.Analyses, float64(work.MostWalks)/10)
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

	if isTerminal(stream) {
		return liveProgress(stream, tenthsOfASecond)
	}

	plain := Watched() || IsRelayed()

	return &Progress{stream: stream, enabled: plain, plain: plain, total: 1, lastFilled: -1, lastShare: -1}
}

// clock starts a run of moments a spinner turns on, and the way to end it.
type clock func() (<-chan time.Time, func())

func tenthsOfASecond() (<-chan time.Time, func()) {
	ticker := time.NewTicker(100 * time.Millisecond)

	return ticker.C, ticker.Stop
}

func liveProgress(stream io.Writer, turning clock) *Progress {
	return &Progress{stream: stream, clock: turning, enabled: true, total: 1, lastFilled: -1, lastShare: -1}
}

func (p *Progress) Status(message string) {
	if !p.Live() {
		return
	}

	p.drawing.Lock()
	defer p.drawing.Unlock()
	p.show(message, "")
}

func (p *Progress) show(phase, label string) {
	p.phase, p.label = phase, label
	if p.ended == nil {
		p.ended, p.stopped = make(chan struct{}), make(chan struct{})
		moments, stop := p.clock()
		go p.spin(moments, stop, p.ended, p.stopped)
	}

	p.draw()
}

func (p *Progress) spin(moments <-chan time.Time, stop func(), ended, stopped chan struct{}) {
	defer close(stopped)
	defer stop()

	for {
		select {
		case <-ended:
			return
		case <-moments:
			p.turn(ended)
		}
	}
}

func (p *Progress) turn(ended chan struct{}) {
	p.drawing.Lock()
	defer p.drawing.Unlock()

	if p.ended != ended {
		return
	}

	p.turns++
	p.draw()
}

func (p *Progress) draw() {
	line := fmt.Sprintf("\r\033[2K%s \033[2m%s\033[0m", spinnerFrames[p.turns%len(spinnerFrames)], p.phase)
	if p.active {
		filled := p.filled()
		line += fmt.Sprintf(" [%s] %d/%d \033[2m%s\033[0m", strings.Repeat("█", filled)+strings.Repeat("░", barWidth-filled), p.current, p.total, p.label)
	}

	fmt.Fprint(p.stream, line)
}

// Above is the console of the run a progress is drawn for: whatever it writes lands on a line of its own, and the
// clock draws the spinner again under it.
func (p *Progress) Above(console Console) Console {
	if !p.Live() {
		return console
	}

	return Console{Out: above{console.Out, p}, Err: above{console.Err, p}}
}

// above is a stream written above a spinner: each write first clears the spinner's line.
type above struct {
	io.Writer
	progress *Progress
}

func (a above) Write(text []byte) (int, error) {
	a.progress.drawing.Lock()
	defer a.progress.drawing.Unlock()

	if a.progress.ended != nil {
		fmt.Fprint(a.progress.stream, "\r\033[2K")
	}

	return a.Writer.Write(text)
}

func (p *Progress) Plan(steps int) {
	if !p.enabled {
		return
	}

	p.drawing.Lock()
	defer p.drawing.Unlock()

	if !p.planned {
		p.planned, p.total = true, 0
	}

	p.total, p.active = max(1, p.total+max(0, steps)), true
}

func (p *Progress) Restart(steps int) {
	if !p.enabled {
		return
	}

	p.drawing.Lock()
	defer p.drawing.Unlock()

	p.total, p.current, p.planned, p.active = max(1, steps), 0, steps > 0, true
	p.lastFilled, p.lastShare = -1, -1
}

func (p *Progress) Start() {
	_, reached := p.standing()
	p.reach(reached, "judging", "")
}

func (p *Progress) Advance(label string) {
	if active, reached := p.standing(); active {
		p.reach(reached+1, "judging", label)
	}
}

func (p *Progress) standing() (active bool, reached int) {
	p.drawing.Lock()
	defer p.drawing.Unlock()

	return p.active, p.current
}

func (p *Progress) Phase(phase string) func(done, total int) {
	base, last, started := 0, 0, false

	return func(done, _ int) {
		if !started || done < last {
			_, base = p.standing()
			started = true
		}

		last = done
		p.reach(base+done, phase, "")
	}
}

func (p *Progress) reach(at int, phase, label string) {
	if !p.enabled {
		return
	}

	p.drawing.Lock()
	defer p.drawing.Unlock()

	p.active = true
	p.current = min(p.total, max(p.current, at))

	if p.plain {
		p.render(phase)

		return
	}

	if filled := p.filled(); filled != p.lastFilled || p.current == p.total || phase != p.phase || p.ended == nil {
		p.lastFilled = filled
		p.show(phase, label)
	}
}

func (p *Progress) Finish() {
	p.drawing.Lock()
	ended, stopped := p.ended, p.stopped
	shown := p.active || ended != nil
	p.ended, p.active = nil, false

	if shown && !p.plain {
		fmt.Fprint(p.stream, "\r\033[2K")
	}
	p.drawing.Unlock()

	if ended != nil {
		close(ended)
		<-stopped
	}
}

func (p *Progress) filled() int {
	return int(math.Round(float64(barWidth*p.current) / float64(p.total)))
}

func (p *Progress) render(phase string) {
	if share := p.current * 100 / p.total; share != p.lastShare || counting(phase) != p.lastWord {
		p.lastShare, p.lastWord = share, counting(phase)
		fmt.Fprintf(p.stream, "%s %d/%d\n", counting(phase), p.current, p.total)
	}
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

	p.drawing.Lock()
	defer p.drawing.Unlock()

	p.total, p.active = max(1, total), true
	p.current = min(p.total, max(0, done))
	if p.plain {
		p.render(phase)

		return
	}

	p.show(phase, "")
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
	if over, isAbove := stream.(above); isAbove {
		return isTerminal(over.Writer)
	}

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
