package cli

import (
	"strings"
	"testing"
	"time"
)

// drawn is a terminal that hands each thing drawn on it to the test, in order.
type drawn chan string

func (d drawn) Write(text []byte) (int, error) {
	d <- string(text)

	return len(text), nil
}

func (d drawn) next(t *testing.T, when string) string {
	t.Helper()

	select {
	case text := <-d:
		return text
	case <-time.After(10 * time.Second):
		t.Fatalf("nothing was drawn %s", when)

		return ""
	}
}

func liveOn(terminal drawn) (*Progress, chan time.Time) {
	moments := make(chan time.Time)

	return liveProgress(terminal, func() (<-chan time.Time, func()) { return moments, func() {} }), moments
}

func TestALiveProgressDrawsAtOnceAndTurnsOnItsClockWhileNoWorkIsReported(t *testing.T) {
	terminal := make(drawn, 64)
	progress, moments := liveOn(terminal)

	progress.Status("starting")
	if len(terminal) != 1 {
		t.Fatalf("a status drew %d times before it returned, where the first word is drawn at once", len(terminal))
	}

	frames := map[string]bool{<-terminal: true}
	for turn := 1; turn <= 3; turn++ {
		moments <- time.Now()
		frame := terminal.next(t, "on a moment of the clock")
		if !strings.Contains(frame, "starting") {
			t.Errorf("turn %d drew %q, without the word of what is being done", turn, frame)
		}
		frames[frame] = true
	}
	if len(frames) != 4 {
		t.Errorf("four draws showed %d different spinners, where each moment turns it", len(frames))
	}

	progress.Plan(12)
	progress.Phase("reading")(3, 12)
	if bar := terminal.next(t, "when the first files were read"); !strings.Contains(bar, "reading") || !strings.Contains(bar, "3/12") {
		t.Errorf("three of twelve files read drew %q, without the phase and its count", bar)
	}

	moments <- time.Now()
	if still := terminal.next(t, "on a moment with no new file read"); !strings.Contains(still, "3/12") {
		t.Errorf("a moment with no new file read drew %q, where the bar stays and the spinner turns", still)
	}

	progress.Finish()
	if cleared := terminal.next(t, "when the progress finished"); cleared != "\r\033[2K" {
		t.Errorf("finishing drew %q, where it clears its line", cleared)
	}

	select {
	case moments <- time.Now():
		t.Error("the clock of a finished progress was still listened to")
	default:
	}
	if len(terminal) != 0 {
		t.Errorf("a finished progress drew %d times more", len(terminal))
	}
}

func TestALineWrittenWhileTheSpinnerTurnsLandsOnALineOfItsOwn(t *testing.T) {
	terminal := make(drawn, 64)
	progress, moments := liveOn(terminal)
	said := &strings.Builder{}
	console := progress.Above(Console{Out: said, Err: said})

	progress.Status("loading the rules")
	<-terminal

	console.Warn("⚠ a rule could not be read")
	if cleared := terminal.next(t, "before the warning"); cleared != "\r\033[2K" {
		t.Errorf("a warning under a turning spinner was preceded by %q, where the spinner's line is cleared first", cleared)
	}
	if said.String() != "⚠ a rule could not be read\n" {
		t.Errorf("the warning reads %q", said.String())
	}

	moments <- time.Now()
	if again := terminal.next(t, "after the warning"); !strings.Contains(again, "loading the rules") {
		t.Errorf("the moment after a warning drew %q, where the spinner comes back under it", again)
	}

	progress.Finish()
	<-terminal
	console.Say("1 sin")
	if len(terminal) != 0 {
		t.Error("a line written after the progress finished cleared a line, where nothing is drawn any more")
	}
}

func TestAStreamWrittenAboveASpinnerIsStillTheTerminalItWraps(t *testing.T) {
	progress, _ := liveOn(make(drawn, 1))
	plain := &strings.Builder{}

	if isTerminal(progress.Above(Console{Out: plain, Err: plain}).Err) {
		t.Error("a stream that is no terminal reads as one once it is written above a spinner")
	}
}
