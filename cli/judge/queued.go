package judge

import (
	"bufio"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const (
	queuedMark = "COMMANDMENTS_QUEUED"
	judgeKind  = "judge"
)

// startedByTheJournal are the variables of a run the journal already started: one its queue started for this judge, an agent's command, or a plugin's own program.
var startedByTheJournal = []string{queuedMark, "JOURNAL_RUNNER_PROCESS", "JOURNAL_PROCESS", "JOURNAL_PLUGIN"}

func insideTheJournal() bool {
	for _, name := range startedByTheJournal {
		if os.Getenv(name) != "" {
			return true
		}
	}

	return false
}

// throughTheQueue hands the judge to the queue of the project's journal and streams the queued run back as it prints, answering its exit code; taken is false when the journal took nothing, so the judge runs here.
func throughTheQueue(space workspace.Workspace, cwd string, in *cli.Input, console cli.Console) (code int, taken bool) {
	journal := space.Journal()
	self, err := os.Executable()
	if journal == "" || err != nil {
		return 0, false
	}

	line := append([]string{"plugin", "queue", workspace.JournalPlugin, "--kind", judgeKind, "--checkout", cwd, "--", "env", queuedMark + "=1", cli.PlainProgress + "=1", self, judgeKind}, in.Raw()...)
	run := exec.Command(journal, line...)
	run.Dir, run.Stdin, run.Stderr = cwd, os.Stdin, console.Err
	output, err := run.StdoutPipe()
	if err != nil || run.Start() != nil {
		return 0, false
	}

	printed := relayed(output, console)

	err = run.Wait()
	var ended *exec.ExitError
	switch {
	case err == nil:
		return 0, true
	case errors.As(err, &ended) && printed:
		return ended.ExitCode(), true
	}

	console.Warn("· the journal did not queue this judge, so it runs here")

	return 0, false
}

const queuedAhead = "waiting: "

// relayed passes the queued run's lines to the terminal as they come and says whether any came: a wait in the queue turns into a spinner and the run's plain progress into the bar a judge draws here, each on one line; anywhere but a terminal every line passes as it is.
func relayed(output io.Reader, console cli.Console) bool {
	bar := cli.NewProgress(console.Err)
	waiting := spinnerOn(bar)
	defer waiting.stop()
	waiting.say("")

	printed := false
	lines := bufio.NewScanner(output)
	lines.Buffer(make([]byte, 64*1024), 1024*1024)
	for lines.Scan() {
		line := lines.Text()
		printed = true

		if !bar.Live() {
			fmt.Fprintln(console.Out, line)

			continue
		}

		if ahead, queued := strings.CutPrefix(line, queuedAhead); queued {
			waiting.say(ahead)

			continue
		}

		waiting.stop()
		if phase, done, total, progress := cli.PlainProgressOf(line); progress {
			bar.At(phase, done, total)

			continue
		}

		bar.Finish()
		fmt.Fprintln(console.Out, line)
	}

	bar.Finish()

	return printed
}

var spinnerFrames = []rune("⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏")

// spinner turns on one line of the terminal while the judge waits for the journal's queue.
type spinner struct {
	bar     *cli.Progress
	mutex   sync.Mutex
	ahead   string
	frame   int
	turning bool
	stopped bool
	done    chan struct{}
}

func spinnerOn(bar *cli.Progress) *spinner {
	return &spinner{bar: bar, done: make(chan struct{})}
}

func (s *spinner) say(ahead string) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	if s.stopped {
		return
	}

	s.ahead = ahead
	s.draw()

	if !s.turning {
		s.turning = true
		go s.turn()
	}
}

func (s *spinner) turn() {
	ticker := time.NewTicker(100 * time.Millisecond)
	defer ticker.Stop()

	for {
		select {
		case <-s.done:
			return
		case <-ticker.C:
			s.mutex.Lock()
			if !s.stopped {
				s.frame++
				s.draw()
			}
			s.mutex.Unlock()
		}
	}
}

func (s *spinner) draw() {
	said := "handing this judge to the journal's queue"
	if s.ahead != "" {
		said = "waiting in the journal's queue, " + s.ahead
	}

	s.bar.Status(string(spinnerFrames[s.frame%len(spinnerFrames)]) + " " + said)
}

func (s *spinner) stop() {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	if s.stopped {
		return
	}

	s.stopped = true
	close(s.done)
	s.bar.Status("")
}
