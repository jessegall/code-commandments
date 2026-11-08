package story

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"time"
)

// Journal is the project's own journal as a story reaches it from inside the container: every command and every hook goes through the relay on the host, which runs it there and hands back what it printed.
type Journal struct {
	address    string
	rehearsing bool
}

// Ran is what one journal command or hook printed and the code it exited with.
type Ran struct {
	Code int    `json:"code"`
	Out  string `json:"out"`
	Err  string `json:"err"`
}

func (r Ran) Text() string {
	return r.Out + r.Err
}

type request struct {
	Kind   string   `json:"kind"`
	Argv   []string `json:"argv,omitempty"`
	Stdin  string   `json:"stdin,omitempty"`
	In     string   `json:"in,omitempty"`
	Path   string   `json:"path,omitempty"`
	Action string   `json:"action,omitempty"`
	Name   string   `json:"name,omitempty"`
	Many   int      `json:"many,omitempty"`
	Pid    string   `json:"pid,omitempty"`
	Actor  string   `json:"actor,omitempty"`
	Role   string   `json:"role,omitempty"`
}

const stepTakesAtMost = 11 * time.Minute

func Reached(address string) Journal {
	return Journal{address: address}
}

func (j Journal) Runs(in string, argv ...string) (Ran, error) {
	return j.ask(request{Kind: "command", Argv: argv, In: in})
}

func (j Journal) RunsAs(in, actor, role string, argv ...string) (Ran, error) {
	return j.ask(request{Kind: "command", Argv: argv, In: in, Actor: actor, Role: role})
}

func (j Journal) Hooks(in, body, actor, role string) (Ran, error) {
	return j.ask(request{Kind: "hook", Stdin: body, In: in, Actor: actor, Role: role})
}

func (j Journal) LooksAt(checkout string) (Ran, error) {
	return j.ask(request{Kind: "look", In: checkout})
}

func (j Journal) Writes(path, text string) (Ran, error) {
	return j.ask(request{Kind: "write", Path: path, Stdin: text})
}

func (j Journal) WritesMany(path, text string, many int) (Ran, error) {
	return j.ask(request{Kind: "write", Path: path, Stdin: text, Many: many})
}

func (j Journal) Rebases(worktree string) (Ran, error) {
	return j.ask(request{Kind: "worktree", Action: "rebase", Name: worktree})
}

func (j Journal) Moves(action string) (Ran, error) {
	return j.ask(request{Kind: "checkout", Action: action})
}

func (j Journal) StartsProcess() (Ran, error) {
	return j.ask(request{Kind: "pid", Action: "start"})
}

func (j Journal) EndsProcess(pid string) (Ran, error) {
	return j.ask(request{Kind: "pid", Action: "end", Pid: pid})
}

func (j Journal) Removes(path string) (Ran, error) {
	return j.ask(request{Kind: "remove", Path: path})
}

func (j Journal) Cuts(worktree string) (Ran, error) {
	return j.ask(request{Kind: "worktree", Action: "cut", Name: worktree})
}

func (j Journal) Drops(worktree string) (Ran, error) {
	return j.ask(request{Kind: "worktree", Action: "drop", Name: worktree})
}

func (j Journal) ask(asked request) (Ran, error) {
	if j.rehearsing {
		return Ran{Out: "created: agent:1\n"}, nil
	}

	connection, err := net.DialTimeout("tcp", j.address, 5*time.Second)
	if err != nil {
		return Ran{}, fmt.Errorf("the story relay at %s does not answer, so no story can reach the journal: %w", j.address, err)
	}
	defer connection.Close()

	if err := connection.SetDeadline(time.Now().Add(stepTakesAtMost)); err != nil {
		return Ran{}, err
	}

	line, err := json.Marshal(asked)
	if err != nil {
		return Ran{}, err
	}

	if _, err := connection.Write(append(line, '\n')); err != nil {
		return Ran{}, err
	}

	answer, err := bufio.NewReader(connection).ReadBytes('\n')
	if err != nil {
		return Ran{}, fmt.Errorf("the story relay hung up before it answered: %w", err)
	}

	var ran Ran
	if err := json.Unmarshal(answer, &ran); err != nil {
		return Ran{}, fmt.Errorf("the story relay answered %q, which is not a result: %w", answer, err)
	}

	return ran, nil
}
