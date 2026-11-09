package hooks

import (
	"errors"
	"os"
	"os/exec"
	"strings"
	"time"
)

// Sending is what became of telling a worker its merge was judged: told when the worker has the words, Retired
// when there is no worker left to tell, and Failed when the journal refused for any other reason, which is told
// to the main agent WITH the reason rather than swallowed.
type Sending int

const (
	ItHasTheWords Sending = iota
	Retired
	Failed
)

const (
	noWorkerLeft    = 5
	workerStillBusy = "has a command running"
	mergeEndsWithin = 30 * time.Second
	betweenSends    = time.Second
)

func TellsTheWorker(project, worker, said string) (Sending, string) {
	if worker == "" || said == "" {
		return Retired, "the merge names no worker to tell"
	}

	journal := journalBinary()
	if journal == "" {
		return Failed, "the journal's own command is not on the path, so the worker could not be told"
	}

	out, err := sendsPastTheMerge(project, journal, worker, said)
	if err == nil {
		return ItHasTheWords, ""
	}

	refusal := strings.TrimSpace(string(out))
	if refusal == "" {
		refusal = err.Error()
	}

	if exitOf(err) == noWorkerLeft {
		return Retired, refusal
	}

	return Failed, refusal
}

func sendsPastTheMerge(project, journal, worker, said string) ([]byte, error) {
	for until := time.Now().Add(mergeEndsWithin); ; time.Sleep(betweenSends) {
		send := exec.Command(journal, "worker", "send", worker, said)
		send.Dir = project
		send.Env = os.Environ()

		logStarted("journal worker send | worker=" + worker)

		out, err := send.CombinedOutput()
		if err == nil || !strings.Contains(string(out), workerStillBusy) || time.Now().After(until) {
			return out, err
		}
	}
}

func exitOf(err error) int {
	var exit *exec.ExitError
	if errors.As(err, &exit) {
		return exit.ExitCode()
	}

	return -1
}

func journalBinary() string {
	if named := os.Getenv("JOURNAL_BIN"); named != "" {
		return named
	}

	found, err := exec.LookPath("journal")
	if err != nil {
		return ""
	}

	return found
}
