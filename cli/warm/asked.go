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

// Answer is what the service judged: what the run prints, and the code it exits with.
type Answer struct {
	Out      string `json:"out"`
	Err      string `json:"err"`
	Code     int    `json:"code"`
	Declined bool   `json:"declined,omitempty"`
	Because  string `json:"because,omitempty"`
}

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
