package judge

import (
	"errors"
	"io"
	"os"
	"os/exec"

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

	line := append([]string{"plugin", "queue", workspace.JournalPlugin, "--kind", judgeKind, "--checkout", cwd, "--", "env", queuedMark + "=1", self, judgeKind}, in.Raw()...)
	run := exec.Command(journal, line...)
	streamed := &printed{to: console.Out}
	run.Dir, run.Stdin, run.Stdout, run.Stderr = cwd, os.Stdin, streamed, console.Err

	err = run.Run()
	var ended *exec.ExitError
	switch {
	case err == nil:
		return 0, true
	case errors.As(err, &ended) && streamed.any:
		return ended.ExitCode(), true
	}

	console.Warn("· the journal did not queue this judge, so it runs here")

	return 0, false
}

// printed passes a run's output on and remembers whether any came: a queued run always prints, a refusal never reaches it.
type printed struct {
	to  io.Writer
	any bool
}

func (p *printed) Write(text []byte) (int, error) {
	p.any = p.any || len(text) > 0

	return p.to.Write(text)
}
