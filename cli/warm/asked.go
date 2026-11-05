package warm

import (
	"bufio"
	"encoding/json"
	"net"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

// Kind is the name the socket of the service that holds the trees is derived from. The name is worked out from
// the project's path, as every language bridge's service socket is, so a run started by anyone computes the same
// name and finds the service without being told where it is: a terminal run is handed no socket and no data
// folder, only the project it is in.
const Kind = "judging"

// Socket is where the service that holds this project's trees listens.
func Socket(project string) string {
	return bridge.ServiceSocket(Kind, project)
}

// Request is a judging a run asks the service to do for it: the folder the run was started in, which is what the
// project, the config and the scope are read from, and the arguments as they were typed.
type Request struct {
	Judge []string `json:"judge"`
	In    string   `json:"in"`
}

// Answer is what the service judged: what the run prints, and the code it exits with. Declined says the service
// did not judge it at all, so the run must do it itself.
type Answer struct {
	Out      string `json:"out"`
	Err      string `json:"err"`
	Code     int    `json:"code"`
	Declined bool   `json:"declined,omitempty"`
	// Because is why a judging was declined, in plain words, for the run to say when it is asked to account for
	// its time. Without it a run that read 6,000 files and one that was answered from memory look identical
	// from outside, and a consumer whose judging took two minutes could only guess which had happened.
	Because string `json:"because,omitempty"`
}

// Asked is the service's answer to a judging, and false where no service answered: nothing listening, a service
// that cannot answer, or an answer that is no answer. A run whose question is not answered reads the tree itself,
// which is what every run did before a tree was ever held.
//
// The timeout is generous because the service may be reading a tree of its own when the question arrives, and a
// run that waited a few seconds and then answered from memory is still far cheaper than one that reads 5286
// files; it is bounded because a service that has wedged must not hold a terminal.
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

	if err := connection.SetDeadline(time.Now().Add(5 * time.Minute)); err != nil {
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

// RequestOf is the judging a line asks for, and false for a line that asks for something else: the service
// answers several kinds of question on one socket.
func RequestOf(line []byte) (Request, bool) {
	var request Request
	if err := json.Unmarshal(line, &request); err != nil || request.Judge == nil {
		return Request{}, false
	}

	return request, true
}

// Answered is the answer as the socket carries it.
func Answered(answer Answer) []byte {
	said, err := json.Marshal(answer)
	if err != nil {
		return []byte("{}\n")
	}

	return append(said, '\n')
}
