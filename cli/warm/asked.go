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
	Judge []string `json:"judge"`
	In    string   `json:"in"`
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

func AskedUntilTheTreeIsThere(project string, request Request, said func(string)) (Answer, bool) {
	return waitingFor(func() (Answer, bool) { return Asked(project, request) }, said, aMomentBetweenAsks,
		waitsForTheTree)
}

func waitingFor(ask func() (Answer, bool), said func(string), apart, atMost time.Duration) (Answer, bool) {
	answer, served := ask()
	if served || !answer.Coming {
		return answer, served
	}

	said("waiting for the tree this project keeps up, because reading it again here would cost more than the wait: " +
		answer.Because)

	for until := time.Now().Add(atMost); time.Now().Before(until); {
		time.Sleep(apart)

		answer, served = ask()
		if served || !answer.Coming {
			return answer, served
		}
	}

	return answer, served
}

const waitsForTheTree = 30 * time.Minute

const aMomentBetweenAsks = time.Second

func Asked(project string, request Request) (Answer, bool) {
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

	line, err := bufio.NewReader(connection).ReadBytes('\n')
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

func RequestOf(line []byte) (Request, bool) {
	var request Request
	if err := json.Unmarshal(line, &request); err != nil || request.Judge == nil {
		return Request{}, false
	}

	return request, true
}

func Answered(answer Answer) []byte {
	said, err := json.Marshal(answer)
	if err != nil {
		return []byte("{}\n")
	}

	return append(said, '\n')
}
