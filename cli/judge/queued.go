package judge

import (
	"bufio"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"
	"os/signal"
	"strings"
	"sync/atomic"
	"syscall"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const (
	queuedMark  = "COMMANDMENTS_QUEUED"
	judgeKind   = "judge"
	queuedAhead = "waiting: "
)

// startedForAnAgent are the variables the journal sets on a command it runs for an agent: a plugin's alias, or an agent's own command.
var startedForAnAgent = []string{"JOURNAL_PLUGIN", "JOURNAL_PROCESS"}

// waitsForTheQueue is a judge the journal started for an agent that its queue has not started yet: it waits in the
// queue judge_at_once holds, so two agents' judges never run side by side. A judge typed in a terminal runs right there.
func waitsForTheQueue() bool {
	if os.Getenv(queuedMark) != "" {
		return false
	}
	for _, name := range startedForAnAgent {
		if os.Getenv(name) != "" {
			return true
		}
	}

	return false
}

// throughTheQueue hands the judge to the queue of the project's journal and streams the queued run back as it prints, answering its exit code; taken is false when the journal took nothing, so the judge runs here.
func throughTheQueue(space workspace.Workspace, cwd string, in *cli.Input, console cli.Console, progress *cli.Progress) (code int, taken bool) {
	journal := space.Journal()
	self, err := os.Executable()
	if journal == "" || err != nil || !queues(journal, cwd) {
		return 0, false
	}

	shown := cli.PlainProgress
	if progress.Live() {
		shown = cli.Relayed
	}

	line := append([]string{"plugin", "queue", workspace.JournalPlugin, "--kind", judgeKind, "--checkout", cwd, "--", "env", queuedMark + "=1", shown + "=1", self, judgeKind}, in.Raw()...)
	run := exec.Command(journal, line...)
	run.Dir, run.Stdin, run.Stderr = cwd, os.Stdin, console.Err
	output, err := run.StdoutPipe()
	if err != nil || run.Start() != nil {
		return 0, false
	}

	stopped, done := passedOn(run.Process)
	defer done()
	printed := relayed(output, console, progress)

	err = run.Wait()
	var ended *exec.ExitError
	switch {
	case err == nil:
		return 0, true
	case errors.As(err, &ended) && (printed || stopped.Load()):
		return ended.ExitCode(), true
	}

	console.Warn("· the journal did not queue this judge, so it runs here")

	return 0, false
}

// passedOn hands Ctrl+C, a kill and a closed terminal to the journal's client until done, so the journal stops the queued or running judge instead of leaving it behind; it says whether one came.
func passedOn(client *os.Process) (*atomic.Bool, func()) {
	stopped := &atomic.Bool{}
	signals := make(chan os.Signal, 1)
	signal.Notify(signals, os.Interrupt, syscall.SIGTERM, syscall.SIGHUP)
	go func() {
		for sig := range signals {
			stopped.Store(true)
			_ = client.Signal(sig)
		}
	}()

	return stopped, func() {
		signal.Stop(signals)
		close(signals)
	}
}

func queues(journal, cwd string) bool {
	asked := exec.Command(journal, "plugin", "queue", "--help")
	asked.Dir = cwd

	return asked.Run() == nil
}

// relayed passes the queued run's lines on as they come and says whether any came: in a terminal a wait in the queue
// turns the spinner and the run's plain progress draws the bar; anywhere else every line passes as it is.
func relayed(output io.Reader, console cli.Console, progress *cli.Progress) bool {
	progress.Status("handing this judge to the journal's queue")

	printed := false
	lines := bufio.NewScanner(output)
	lines.Buffer(make([]byte, 64*1024), 1024*1024)
	for lines.Scan() {
		line := lines.Text()
		printed = true

		if !progress.Live() {
			fmt.Fprintln(console.Out, line)

			continue
		}

		if ahead, queued := strings.CutPrefix(line, queuedAhead); queued {
			progress.Status("waiting in the journal's queue, " + ahead)

			continue
		}

		if phase, done, total, read := cli.PlainProgressOf(line); read {
			progress.At(phase, done, total)

			continue
		}

		fmt.Fprintln(console.Out, line)
	}

	return printed
}
