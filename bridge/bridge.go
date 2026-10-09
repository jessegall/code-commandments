// Package bridge runs a language's bridge and reads the generic tree it writes (contract/CONTRACT.md).
package bridge

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"os"
	"os/exec"
	"strings"
	"sync"
	"sync/atomic"
	"syscall"
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

// Once runs the bridge over the paths and reads the stream it writes; a bridge that fails answers why, with the stream
// as far as it wrote it.
func Once(command []string, paths ...string) (*contract.Stream, error) {
	return OnceTallied(command, func() {}, paths...)
}

// OnceTallied runs the bridge over the paths as Once does, telling tally of each file the moment the stream holds it.
func OnceTallied(command []string, tally func(), paths ...string) (*contract.Stream, error) {
	stream, errs, ran, err := RunTallied(command, tally, paths...)
	if !ran {
		return stream, Failed(command, err, errs)
	}

	return stream, err
}

// Run runs the command and reads the stream it writes as it writes it, so a large stream is never held as text as
// well as read; with what it wrote to stderr, and whether it ran to a clean exit. A command that failed answers
// why; one that ran answers the stream, or why it broke the contract. Either way the stream is answered as far as it
// was read.
func Run(command []string, arguments ...string) (stream *contract.Stream, errs string, ran bool, err error) {
	return RunTallied(command, func() {}, arguments...)
}

// RunTallied runs the command as Run does, telling tally of each file the moment the stream holds it.
func RunTallied(command []string, tally func(), arguments ...string) (stream *contract.Stream, errs string, ran bool, err error) {
	limit, err := quietLimit()
	if err != nil {
		return nil, "", false, err
	}
	var failure bytes.Buffer
	process, stop := launch(command, arguments...)
	defer stop()
	process.Stderr = &failure
	out, err := process.StdoutPipe()
	if err != nil {
		return nil, "", false, err
	}
	if err := process.Start(); err != nil {
		return nil, failure.String(), false, err
	}
	output := watch(out, limit, func() {
		stop()
		out.Close()
	})
	stream, read := contract.NewReader(output).Tallied(tally)
	io.Copy(io.Discard, output)
	exited := process.Wait()
	if output.silent.Load() {
		return stream, failure.String(), false, Silent{For: limit}
	}
	if exited != nil {
		return stream, failure.String(), false, exited
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
	stop       func()
	mu         sync.Mutex
}

// Serve starts the bridge with --serve.
func Serve(command []string) (*Server, error) {
	limit, err := quietLimit()
	if err != nil {
		return nil, err
	}
	process, cancel := launch(command, "--serve")
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
		cancel()

		return nil, Failed(command, err, "")
	}
	errs := readStderr(pipe)
	stop := func() {
		cancel()
		output.Close()
	}

	return &Server{command: command, process: process, input: input, output: contract.NewReader(watch(output, limit, stop)), errs: errs, stop: stop}, nil
}

// Ask sends one request and reads the whole stream the bridge answers with.
func (s *Server) Ask(request Request) (*contract.Stream, error) {
	return s.AskTallied(request, func() {})
}

// AskTallied sends one request as Ask does, telling tally of each file the moment the answer holds it.
func (s *Server) AskTallied(request Request, tally func()) (*contract.Stream, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	line, err := json.Marshal(request)
	if err != nil {
		return nil, err
	}
	if _, err := s.input.Write(append(line, '\n')); err != nil {
		return nil, Failed(s.command, err, s.stderr())
	}
	stream, err := s.output.Tallied(tally)
	if err != nil {
		return nil, Failed(s.command, err, s.stderr())
	}

	return stream, nil
}

// AskEach sends one request and hands each line of the stream the bridge answers with to each, as it arrives, with
// the bytes it was read from: an answer too large to hold whole is read this way. An error each returns is its own,
// never the bridge's failure.
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
	var refused error
	read := s.output.Each(func(line contract.Line, raw []byte) error {
		refused = each(line, raw)

		return refused
	})
	if refused != nil {
		return refused
	}
	if read != nil {
		return Failed(s.command, read, s.stderr())
	}

	return nil
}

// Close ends the bridge process, or lets go of the service's socket, which the session keeps up. A bridge that does
// not end when its input does is stopped a grace later.
func (s *Server) Close() error {
	if s.connection != nil {
		return s.connection.Close()
	}
	s.input.(io.Closer).Close()
	lingering := time.AfterFunc(grace, s.stop)
	defer lingering.Stop()

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

// Reason is why the bridge failed, in one line: the silence it was stopped for, else the first line it wrote to
// stderr, else what ended the read.
func (e *BridgeFailed) Reason() string {
	var silent Silent
	if errors.As(e.Cause, &silent) {
		return silent.Error()
	}
	if said, _, _ := strings.Cut(strings.TrimSpace(e.Stderr), "\n"); said != "" {
		return said
	}

	return e.Cause.Error()
}

// quietVariable names how long a bridge may stay silent while it is read, as a Go duration; 0 waits for ever.
const quietVariable = "COMMANDMENTS_BRIDGE_QUIET"

// quiet is how long a bridge may stay silent while it is read when $COMMANDMENTS_BRIDGE_QUIET names no other: over
// a hundred times the longest the C# bridge was measured to think between two lines, 1.8 s across the whole of a
// 14,307-file solution.
const quiet = 5 * time.Minute

// grace is how long a bridge asked to end is given before it is killed.
const grace = 10 * time.Second

// quietLimit is how long a bridge may stay silent while it is read.
func quietLimit() (time.Duration, error) {
	named := os.Getenv(quietVariable)
	if named == "" {
		return quiet, nil
	}
	limit, err := time.ParseDuration(named)
	if err != nil || limit < 0 {
		return 0, fmt.Errorf("$%s is %q, not a duration such as 10m (0 waits for ever)", quietVariable, named)
	}

	return limit, nil
}

// launch is the bridge's process, and what stops it: asked to end with SIGTERM, so a launcher script stops the
// container it started, and killed when it has not ended a grace later.
func launch(command []string, arguments ...string) (*exec.Cmd, context.CancelFunc) {
	running, stop := context.WithCancel(context.Background())
	process := exec.CommandContext(running, command[0], append(command[1:], arguments...)...)
	process.Cancel = func() error {
		return process.Process.Signal(syscall.SIGTERM)
	}
	process.WaitDelay = grace

	return process, stop
}

// Silent is a bridge that wrote nothing for as long as a read may wait, so it was taken for hung and stopped.
type Silent struct {
	For time.Duration
}

func (e Silent) Error() string {
	return fmt.Sprintf("it wrote nothing for %s, so it was taken for hung and stopped ($%s sets how long a bridge may stay silent)", e.For, quietVariable)
}

// watched is a bridge's output, read with a limit on how long one read may wait: past it the bridge is stopped and
// the read ends Silent. Only the wait inside a read counts, so a caller that takes its time between reads, or
// between requests to a served bridge, never stops one that is answering.
type watched struct {
	source io.Reader
	limit  time.Duration
	stop   func()
	silent atomic.Bool
}

// watch reads the source with the limit, stopping the bridge when a read waits past it; a limit of 0 waits for ever.
func watch(source io.Reader, limit time.Duration, stop func()) *watched {
	return &watched{source: source, limit: limit, stop: stop}
}

func (w *watched) Read(p []byte) (int, error) {
	if w.limit == 0 {
		return w.source.Read(p)
	}
	timer := time.AfterFunc(w.limit, func() {
		w.silent.Store(true)
		w.stop()
	})
	n, err := w.source.Read(p)
	timer.Stop()
	if w.silent.Load() {
		return n, Silent{For: w.limit}
	}

	return n, err
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
