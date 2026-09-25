package scribes

import (
	"slices"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
)

// Reader is how one engine reads the codebase a served bridge answers a request with.
type Reader func(server *bridge.Server, request bridge.Request) (*engine.Codebase, error)

// Scanner reads one engine's files under a pass's roots as its drafts leave them, through one bridge kept serving
// for the run.
type Scanner struct {
	server   *bridge.Server
	read     Reader
	roots    []string
	drafts   Rewrites
	codebase *engine.Codebase
	frozen   map[string]bool
}

// Serve starts the bridge the command runs, serving, read by read.
func Serve(command []string, read Reader) (*Scanner, error) {
	server, err := bridge.Serve(command)
	if err != nil {
		return nil, err
	}

	return &Scanner{server: server, read: read, frozen: map[string]bool{}}, nil
}

// Scan is the codebase under the pass's roots read through its drafts; the same drafts read the same codebase.
func (s *Scanner) Scan(pass Pass) (*engine.Codebase, error) {
	if s.codebase != nil && slices.Equal(s.roots, pass.Roots) && s.drafts.Equal(pass.Drafts) {
		return s.codebase, nil
	}
	codebase, err := s.read(s.server, bridge.Request{Paths: pass.Roots, Contents: pass.Drafts.Contents()})
	if err != nil {
		return nil, err
	}
	for _, file := range codebase.Files() {
		s.frozen[file.Path] = file.IsFrozen()
	}
	s.roots, s.drafts, s.codebase = slices.Clone(pass.Roots), pass.Drafts, codebase

	return codebase, nil
}

// IsFrozen says whether a file the scanner has read is frozen.
func (s *Scanner) IsFrozen(path string) bool {
	return s.frozen[path]
}

// Close stops the bridge.
func (s *Scanner) Close() error {
	return s.server.Close()
}

// Frozens is frozen when any of its scanners has read the file frozen.
type Frozens []*Scanner

// IsFrozen says whether any scanner read the file frozen.
func (f Frozens) IsFrozen(path string) bool {
	for _, scanner := range f {
		if scanner.IsFrozen(path) {
			return true
		}
	}

	return false
}
