// Package bridge runs a language's bridge and reads the generic tree it writes (contract/CONTRACT.md).
package bridge

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"os/exec"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Request is what a served bridge is asked: the paths whose files it reads, and the ones it writes to be judged.
type Request struct {
	Paths  []string `json:"paths"`
	Write  []string `json:"write,omitempty"`
	Python string   `json:"python,omitempty"`
}

// Once runs the bridge over the paths and reads the stream it writes.
func Once(command []string, paths ...string) (*contract.Stream, error) {
	var out, errs bytes.Buffer
	process := exec.Command(command[0], append(command[1:], paths...)...)
	process.Stdout, process.Stderr = &out, &errs
	if err := process.Run(); err != nil {
		return nil, Failed(command, err, errs.String())
	}

	return contract.ReadAll(&out)
}

// Server is a bridge kept running with --serve, answering one request at a time from what it holds in memory.
type Server struct {
	command []string
	process *exec.Cmd
	input   io.WriteCloser
	output  *contract.Reader
	errs    *bytes.Buffer
	mu      sync.Mutex
}

// Serve starts the bridge with --serve.
func Serve(command []string) (*Server, error) {
	process := exec.Command(command[0], append(command[1:], "--serve")...)
	input, err := process.StdinPipe()
	if err != nil {
		return nil, err
	}
	output, err := process.StdoutPipe()
	if err != nil {
		return nil, err
	}
	errs := &bytes.Buffer{}
	process.Stderr = errs
	if err := process.Start(); err != nil {
		return nil, Failed(command, err, "")
	}

	return &Server{command: command, process: process, input: input, output: contract.NewReader(output), errs: errs}, nil
}

// Ask sends one request and reads the whole stream the bridge answers with.
func (s *Server) Ask(request Request) (*contract.Stream, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	line, err := json.Marshal(request)
	if err != nil {
		return nil, err
	}
	if _, err := s.input.Write(append(line, '\n')); err != nil {
		return nil, Failed(s.command, err, s.errs.String())
	}
	stream, err := s.output.Stream()
	if err != nil {
		return nil, Failed(s.command, err, s.errs.String())
	}

	return stream, nil
}

// Close ends the bridge.
func (s *Server) Close() error {
	s.input.Close()

	return s.process.Wait()
}

// BridgeFailed is a bridge that exited or answered outside the contract, with what it wrote to stderr.
type BridgeFailed struct {
	Command []string
	Cause   error
	Stderr  string
}

// Failed is the failure of the bridge the command runs.
func Failed(command []string, cause error, stderr string) *BridgeFailed {
	return &BridgeFailed{Command: command, Cause: cause, Stderr: stderr}
}

func (e *BridgeFailed) Error() string {
	return fmt.Sprintf("the bridge %v failed: %v\n%s", e.Command, e.Cause, e.Stderr)
}

func (e *BridgeFailed) Unwrap() error {
	return e.Cause
}
