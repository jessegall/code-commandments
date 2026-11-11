package warm

import (
	"bufio"
	"encoding/json"
	"net"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

const Kind = "judging"

func Socket(project string) string {
	return bridge.ServiceSocket(Kind, project)
}

// Request is a judging a run asks the service to do for it: the folder the run was started in, which is what the project, the config and the scope are read from, and the arguments as they were typed.
type Request struct {
	Judge    []string `json:"judge"`
	In       string   `json:"in"`
	Progress bool     `json:"progress,omitempty"`
	Agent    bool     `json:"agent,omitempty"`
}

// Progress is one line of how far a judging has got, sent ahead of its answer to a run that asked for it.
type Progress struct {
	Line string `json:"progress"`
}

// Answer is what the service judged: what the run prints, the code it exits with, and, where it judged nothing, whether the tree it would have answered from is on its way, so the run waits for it instead of reading the whole project itself.
type Answer struct {
	Out      string `json:"out"`
	Err      string `json:"err"`
	Code     int    `json:"code"`
	Declined bool   `json:"declined,omitempty"`
	Because  string `json:"because,omitempty"`
	Coming   bool   `json:"coming,omitempty"`
}

func AskedUntilTheTreeIsThere(project string, request Request, said, progressed func(string)) (Answer, bool) {
	return waitingFor(func() (Answer, bool) { return Asked(project, request, progressed) }, said, aMomentBetweenAsks,
		waitsForTheTree, waitsForTheServiceToReturn)
}

func waitingFor(ask func() (Answer, bool), said func(string), apart, atMost, silence time.Duration) (Answer, bool) {
	answer, served := ask()
	if served || !answer.Coming {
		return answer, served
	}

	said("waiting for the tree this project keeps up, because reading it again here would cost more than the wait: " +
		answer.Because)

	heard, told := time.Now(), time.Now()

	for until := time.Now().Add(atMost); time.Now().Before(until); {
		time.Sleep(apart)

		if time.Since(told) >= atMost/timesToldWhileWaiting {
			said("still waiting for the tree: " + answer.Because)
			told = time.Now()
		}

		answer, served = ask()
		if !served && !answer.Declined {
			if time.Since(heard) >= silence {
				return answer, served
			}

			continue
		}

		heard = time.Now()

		if served || !answer.Coming {
			return answer, served
		}
	}

	return answer, served
}

const waitsForTheTree = 30 * time.Minute

const waitsForTheServiceToReturn = 5 * time.Minute

const aMomentBetweenAsks = time.Second

const timesToldWhileWaiting = 60

func Asked(project string, request Request, progressed func(string)) (Answer, bool) {
	connection, err := net.DialTimeout("unix", Socket(project), 2*time.Second)
	if err != nil {
		return Answer{}, false
	}
	defer connection.Close()

	asked, err := json.Marshal(request)
	if err != nil {
		return Answer{}, false
	}

	if err := connection.SetDeadline(time.Now().Add(waitsForTheTree)); err != nil {
		return Answer{}, false
	}

	if _, err := connection.Write(append(asked, '\n')); err != nil {
		return Answer{}, false
	}

	reader := bufio.NewReader(connection)

	line, err := reader.ReadBytes('\n')
	for ; progressedBy(line, progressed); line, err = reader.ReadBytes('\n') {
	}

	if err != nil && len(line) == 0 {
		return Answer{}, false
	}

	var answer Answer
	if err := json.Unmarshal(line, &answer); err != nil {
		return Answer{}, false
	}

	if answer.Declined {
		return answer, false
	}

	return answer, true
}

func progressedBy(line []byte, progressed func(string)) bool {
	var progress Progress
	if err := json.Unmarshal(line, &progress); err != nil || progress.Line == "" {
		return false
	}

	progressed(progress.Line)

	return true
}

func RequestOf(line []byte) (Request, bool) {
	var request Request
	if err := json.Unmarshal(line, &request); err != nil || request.Judge == nil {
		return Request{}, false
	}

	return request, true
}

func Progressed(line string) []byte {
	said, err := json.Marshal(Progress{Line: line})
	if err != nil {
		return nil
	}

	return append(said, '\n')
}

func Answered(answer Answer) []byte {
	said, err := json.Marshal(answer)
	if err != nil {
		return []byte("{}\n")
	}

	return append(said, '\n')
}
