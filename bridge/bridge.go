// Package bridge runs a language's bridge and reads the generic tree it writes (contract/CONTRACT.md).
package bridge

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"os/exec"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// Request is what a served bridge is asked: the paths whose files it reads, the ones it writes to be judged, and
// the text drafted for any file, read in place of the disk's.
type Request struct {
	Paths    []string          `json:"paths"`
	Write    []string          `json:"write,omitempty"`
	Python   string            `json:"python,omitempty"`
	Contents map[string]string `json:"contents,omitempty"`
}

// Once runs the bridge over the paths and reads the stream it writes.
func Once(command []string, paths ...string) (*contract.Stream, error) {
	stream, errs, ran, err := Run(command, paths...)
	if !ran {
		return nil, Failed(command, err, errs)
	}

	return stream, err
}

// Run runs the command and reads the stream it writes as it writes it, so a large stream is never held as text as
// well as read; with what it wrote to stderr, and whether it ran to a clean exit. A command that failed answers
// why; one that ran answers the stream, or why it broke the contract.
func Run(command []string, arguments ...string) (stream *contract.Stream, errs string, ran bool, err error) {
	var failure bytes.Buffer
	process := exec.Command(command[0], append(command[1:], arguments...)...)
	process.Stderr = &failure
	out, err := process.StdoutPipe()
	if err != nil {
		return nil, "", false, err
	}
	if err := process.Start(); err != nil {
		return nil, failure.String(), false, err
	}
	stream, read := contract.ReadAll(out)
	io.Copy(io.Discard, out)
	if err := process.Wait(); err != nil {
		return nil, failure.String(), false, err
	}

	return stream, failure.String(), true, read
}

// Server is a bridge kept running, answering one request at a time: a process started with --serve, or a service
// a session keeps up, reached over its socket.
type Server struct {
	command    []string
	process    *exec.Cmd
	input      io.Writer
	output     *contract.Reader
	errs       *stderr
	connection net.Conn
	mu         sync.Mutex
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
	pipe, err := process.StderrPipe()
	if err != nil {
		return nil, err
	}
	if err := process.Start(); err != nil {
		return nil, Failed(command, err, "")
	}
	errs := readStderr(pipe)

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
		return nil, Failed(s.command, err, s.stderr())
	}
	stream, err := s.output.Stream()
	if err != nil {
		return nil, Failed(s.command, err, s.stderr())
	}

	return stream, nil
}

// AskEach sends one request and hands each line of the stream the bridge answers with to each, as it arrives, with
// the bytes it was read from: an answer too large to hold whole is read this way.
func (s *Server) AskEach(request Request, each func(line contract.Line, raw []byte) error) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	line, err := json.Marshal(request)
	if err != nil {
		return err
	}
	if _, err := s.input.Write(append(line, '\n')); err != nil {
		return Failed(s.command, err, s.stderr())
	}
	if err := s.output.Each(each); err != nil {
		return Failed(s.command, err, s.stderr())
	}

	return nil
}

// Close ends the bridge process, or lets go of the service's socket, which the session keeps up.
func (s *Server) Close() error {
	if s.connection != nil {
		return s.connection.Close()
	}
	s.input.(io.Closer).Close()

	return s.process.Wait()
}

// stderr is what the bridge process wrote to stderr; a service writes its own elsewhere.
func (s *Server) stderr() string {
	if s.errs == nil {
		return ""
	}

	return s.errs.String()
}

// stderr is a served bridge's stderr, read as the bridge writes it.
type stderr struct {
	mu   sync.Mutex
	text bytes.Buffer
	done chan struct{}
}

// readStderr reads the pipe until the bridge closes it.
func readStderr(pipe io.Reader) *stderr {
	errs := &stderr{done: make(chan struct{})}
	go func() {
		defer close(errs.done)
		chunk := make([]byte, 32*1024)
		for {
			n, err := pipe.Read(chunk)
			errs.mu.Lock()
			errs.text.Write(chunk[:n])
			errs.mu.Unlock()
			if err != nil {
				return
			}
		}
	}()

	return errs
}

// String is what the bridge wrote: all of it once the bridge has closed its stderr, else what it wrote in the
// second it is given to, so a bridge that failed yet lives on cannot hold its caller.
func (e *stderr) String() string {
	select {
	case <-e.done:
	case <-time.After(time.Second):
	}
	e.mu.Lock()
	defer e.mu.Unlock()

	return e.text.String()
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

// Unavailable is a bridge that cannot run on this machine: its language goes unjudged, and everything else is judged.
type Unavailable interface {
	error
	unavailable()
}

// ToolchainMissing is a bridge whose language's own toolchain, the one thing it leans on, is not installed here.
type ToolchainMissing struct {
	Language string
	Need     string
}

// Error says what to install, and what is and is not judged.
func (e ToolchainMissing) Error() string {
	return "the " + e.Language + " bridge needs " + e.Need + ", which is not installed here, so " + e.Language + " is not judged; everything else is"
}

func (ToolchainMissing) unavailable() {}
