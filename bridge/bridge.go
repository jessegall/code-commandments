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
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"syscall"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// Request is what a served bridge is asked: the paths whose files it reads, the ones it writes to be judged, and the text drafted for any file, read in place of the disk's.
type Request struct {
	Paths    []string          `json:"paths"`
	Write    []string          `json:"write,omitempty"`
	Python   string            `json:"python,omitempty"`
	Contents map[string]string `json:"contents,omitempty"`
}

func Once(command []string, paths ...string) (*contract.Stream, error) {
	return OnceTallied(command, func() {}, paths...)
}

func OnceTallied(command []string, tally func(), paths ...string) (*contract.Stream, error) {
	stream, errs, ran, err := RunTallied(command, tally, paths...)
	if !ran {
		return stream, Failed(command, err, errs)
	}

	return stream, err
}

func Run(command []string, arguments ...string) (stream *contract.Stream, errs string, ran bool, err error) {
	return RunTallied(command, func() {}, arguments...)
}

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

// Server is a bridge kept running, answering one request at a time: a process started with --serve, or a service a session keeps up, reached over its socket.
type Server struct {
	command    []string
	process    *exec.Cmd
	input      io.Writer
	output     *contract.Reader
	errs       *stderr
	connection net.Conn
	stop       func()
	quiet      time.Duration
	heaviest   int
	mu         sync.Mutex
}

func Serve(command []string) (*Server, error) {
	limit, err := quietLimit()
	if err != nil {
		return nil, err
	}
	heaviest, err := memoryLimit()
	if err != nil {
		return nil, err
	}

	server := &Server{command: command, quiet: limit, heaviest: heaviest}
	if err := server.start(); err != nil {
		return nil, err
	}

	return server, nil
}

func (s *Server) start() error {
	process, cancel := launch(s.command, "--serve")
	input, err := process.StdinPipe()
	if err != nil {
		return err
	}
	output, err := process.StdoutPipe()
	if err != nil {
		return err
	}
	pipe, err := process.StderrPipe()
	if err != nil {
		return err
	}
	if err := process.Start(); err != nil {
		cancel()

		return Failed(s.command, err, "")
	}
	stop := func() {
		cancel()
		output.Close()
	}

	s.process, s.input, s.errs, s.stop = process, input, readStderr(pipe), stop
	s.output = contract.NewReader(watch(output, s.quiet, stop))

	return nil
}

func (s *Server) ended() error {
	s.input.(io.Closer).Close()
	lingering := time.AfterFunc(grace, s.stop)
	defer lingering.Stop()

	return s.process.Wait()
}

func (s *Server) pid() int {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.process.Process.Pid
}

func (s *Server) released() {
	if s.process == nil || s.heaviest == 0 {
		s.mu.Unlock()

		return
	}

	go func() {
		defer s.mu.Unlock()
		s.renewedWhenHeavy()
	}()
}

func (s *Server) renewedWhenHeavy() {
	held := footprintOf(s.process.Process.Pid)
	if held <= s.heaviest {
		return
	}

	fmt.Fprintf(os.Stderr, "the bridge %s held %d MB, past its limit of %d MB, so it is stopped and a new one answers the next request\n", s.command[0], held, s.heaviest)
	s.ended()

	if err := s.start(); err != nil {
		fmt.Fprintln(os.Stderr, "the new bridge did not start: "+err.Error())
	}
}

func (s *Server) Ask(request Request) (*contract.Stream, error) {
	return s.AskTallied(request, func() {})
}

func (s *Server) AskTallied(request Request, tally func()) (*contract.Stream, error) {
	s.mu.Lock()
	defer s.released()
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

func (s *Server) AskEach(request Request, each func(line contract.Line, raw []byte) error) error {
	s.mu.Lock()
	defer s.released()
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

func (s *Server) Close() error {
	if s.connection != nil {
		return s.connection.Close()
	}

	s.mu.Lock()
	defer s.mu.Unlock()

	return s.ended()
}

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

func Failed(command []string, cause error, stderr string) *BridgeFailed {
	return &BridgeFailed{Command: command, Cause: cause, Stderr: stderr}
}

func (e *BridgeFailed) Error() string {
	return fmt.Sprintf("the bridge %v failed: %v\n%s", e.Command, e.Cause, e.Stderr)
}

func (e *BridgeFailed) Unwrap() error {
	return e.Cause
}

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

const quietVariable = "COMMANDMENTS_BRIDGE_QUIET"

const quiet = 5 * time.Minute

const grace = 10 * time.Second

const stopsWithin = 2 * time.Second

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

const memoryVariable = "COMMANDMENTS_BRIDGE_MEMORY"

const memory = 2048

func memoryLimit() (int, error) {
	named := os.Getenv(memoryVariable)
	if named == "" {
		return memory, nil
	}
	limit, err := strconv.Atoi(named)
	if err != nil || limit < 0 {
		return 0, fmt.Errorf("$%s is %q, not a number of megabytes such as 2048 (0 sets no limit)", memoryVariable, named)
	}

	return limit, nil
}

var Launching = func(command []string, arguments int) {}

var everyBridge, StopEveryBridge = context.WithCancel(context.Background())

func launch(command []string, arguments ...string) (*exec.Cmd, context.CancelFunc) {
	Launching(command, len(arguments))

	running, stop := context.WithCancel(everyBridge)
	process := exec.CommandContext(running, command[0], append(command[1:], arguments...)...)
	process.Cancel = func() error {
		return process.Process.Signal(syscall.SIGTERM)
	}
	process.WaitDelay = stopsWithin

	return process, stop
}

// Silent is a bridge that wrote nothing for as long as a read may wait, so it was taken for hung and stopped.
type Silent struct {
	For time.Duration
}

func (e Silent) Error() string {
	return fmt.Sprintf("it wrote nothing for %s, so it was taken for hung and stopped ($%s sets how long a bridge may stay silent)", e.For, quietVariable)
}

// watched is a bridge's output, read with a limit on how long one read may wait: past it the bridge is stopped and the read ends Silent.
type watched struct {
	source io.Reader
	limit  time.Duration
	stop   func()
	silent atomic.Bool
}

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

func (e ToolchainMissing) Error() string {
	return "the " + e.Language + " bridge needs " + e.Need + ", which is not installed here, so " + e.Language + " is not judged; everything else is"
}

func (ToolchainMissing) unavailable() {}
