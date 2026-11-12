package scribes

import (
	"maps"
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

// Reader is how one engine reads the codebase a served bridge answers a request with.
type Reader func(server *bridge.Server, request bridge.Request) (*engine.Codebase, error)

// Scanner reads one engine's files under a pass's roots as its drafts leave them, through one bridge kept serving for the run.
type Scanner struct {
	server   *bridge.Server
	read     Reader
	roots    []string
	drafts   Rewrites
	codebase *engine.Codebase
	frozen   map[string]bool
	owned    bool
}

func Serve(command []string, read Reader) (*Scanner, error) {
	server, err := bridge.Serve(command)
	if err != nil {
		return nil, err
	}
	scanner := Over(server, read)
	scanner.owned = true

	return scanner, nil
}

func Over(server *bridge.Server, read Reader) *Scanner {
	return &Scanner{server: server, read: read, frozen: map[string]bool{}}
}

func (s *Scanner) Scan(pass Pass) (*engine.Codebase, error) {
	if s == nil {
		return engine.Load(), nil
	}

	if s.codebase != nil && slices.Equal(s.roots, pass.Roots) && s.drafts.Equal(pass.Drafts) {
		return s.codebase, nil
	}
	contents := pass.Drafts.Contents()
	codebase, err := s.read(s.server, bridge.Request{Paths: filesUnder(pass.Roots, contents), Contents: contents})
	if err != nil {
		return nil, err
	}
	for _, file := range codebase.Files() {
		s.frozen[file.Path] = file.IsFrozen()
	}
	s.roots, s.drafts, s.codebase = slices.Clone(pass.Roots), pass.Drafts, codebase

	return codebase, nil
}

func (s *Scanner) Parse(sources map[string]string) (*engine.Codebase, error) {
	made, err := os.MkdirTemp("", "commandments-parse-")
	if err != nil {
		return nil, err
	}
	defer os.RemoveAll(made)
	folder, err := filepath.EvalSymlinks(made)
	if err != nil {
		return nil, err
	}
	contents := map[string]string{}
	for name, source := range sources {
		contents[folder+"/"+name] = source
	}

	return s.read(s.server, bridge.Request{Paths: slices.Sorted(maps.Keys(contents)), Contents: contents})
}

func filesUnder(roots []string, contents map[string]string) []string {
	files := bridge.Listed(roots...)
	for path := range contents {
		if !slices.Contains(files, path) && slices.ContainsFunc(roots, func(root string) bool { return walk.InTree(root, path) }) {
			files = append(files, path)
		}
	}
	slices.Sort(files)

	return files
}

func (s *Scanner) ReadFile(path string) (*engine.Codebase, error) {
	return s.read(s.server, bridge.Request{Paths: []string{path}})
}

func (s *Scanner) IsFrozen(path string) bool {
	return s.frozen[path]
}

func (s *Scanner) Close() error {
	if s == nil || !s.owned {
		return nil
	}

	return s.server.Close()
}

// Frozens is frozen when any of its scanners has read the file frozen.
type Frozens []*Scanner

func (f Frozens) IsFrozen(path string) bool {
	for _, scanner := range f {
		if scanner.IsFrozen(path) {
			return true
		}
	}

	return false
}
