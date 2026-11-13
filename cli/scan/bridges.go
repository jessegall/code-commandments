package scan

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"sync"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

// Bridge is one language's bridge: the name its socket and its service carry, the word its has- verb uses, the languages it reads, the name a reader of a report sees, how it is reached, whether its files may be read in shares, and what it must be told once where reaching it says something a person should know.
type Bridge struct {
	Kind       string
	Word       string
	Languages  []source.Language
	Label      string
	Projects   *bridge.Projects
	InOwnOrder bool
	Reach      Reach
	Notice     func() string
}

// Reach is how a bridge is reached.
type Reach interface {
	Open(held Bridge, roots []string) (bridge.Asker, error)
	Own(held Bridge, roots []string) (bridge.Asker, bool, error)
}

// Process is the reach of a bridge that runs as a process: the command that starts it, and, for a bridge whose absence a person must be told about by name, how its failures are named.
type Process struct {
	Command func(roots []string) ([]string, error)
	Named   func(err error) error
}

func (p Process) Open(held Bridge, roots []string) (bridge.Asker, error) {
	return &process{held: held, reach: p, roots: roots}, nil
}

func (p Process) Own(held Bridge, roots []string) (bridge.Asker, bool, error) {
	command, err := p.Command(roots)
	if err != nil {
		return nil, true, p.named(err)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		return nil, true, p.named(err)
	}

	return &kept{held: held, reach: p, roots: roots, server: server}, true, nil
}

func (p Process) named(err error) error {
	if err == nil || p.Named == nil {
		return err
	}

	return p.Named(err)
}

// InProcess is the reach of a bridge the binary carries: it answers from this very process, so there is no command to start, no socket to dial and no service to keep up, and a run of the tool leaves nothing behind.
type InProcess struct {
	Stream func(paths, write []string) (*contract.Stream, error)
}

func (p InProcess) Open(held Bridge, roots []string) (bridge.Asker, error) {
	return inProcess{reach: p}, nil
}

func (p InProcess) Own(held Bridge, roots []string) (bridge.Asker, bool, error) {
	return nil, false, nil
}

// inProcess answers a request by building the tree here and now.
type inProcess struct {
	reach InProcess
}

func (p inProcess) AskTallied(request bridge.Request, tally func()) (*contract.Stream, error) {
	stream, err := p.reach.Stream(request.Paths, request.Write)
	if err != nil {
		return nil, err
	}
	for range stream.Files {
		tally()
	}

	return stream, nil
}

func (p inProcess) AskEach(request bridge.Request, each func(line contract.Line, raw []byte) error) error {
	stream, err := p.reach.Stream(request.Paths, request.Write)
	if err != nil {
		return err
	}
	for _, file := range stream.Files {
		raw, err := json.Marshal(map[string]any{"file": file})
		if err != nil {
			return err
		}
		if err := each(contract.Line{File: file}, raw); err != nil {
			return err
		}
	}

	return nil
}

func (p inProcess) Close() error {
	return nil
}

// kept is the bridge a service holds for the whole session: asked while it answers, and started afresh once it has gone.
type kept struct {
	held   Bridge
	reach  Process
	roots  []string
	server *bridge.Server
	mu     sync.Mutex
}

func (k *kept) AskTallied(request bridge.Request, tally func()) (*contract.Stream, error) {
	k.mu.Lock()
	defer k.mu.Unlock()

	server, err := k.serving()
	if err != nil {
		return nil, err
	}

	stream, err := server.AskTallied(request, tally)
	if err == nil {
		return stream, nil
	}
	k.gone(err)

	if server, err = k.serving(); err != nil {
		return nil, err
	}

	stream, err = server.AskTallied(request, tally)
	if err != nil {
		k.gone(err)
	}

	return stream, k.reach.named(err)
}

func (k *kept) AskEach(request bridge.Request, each func(line contract.Line, raw []byte) error) error {
	k.mu.Lock()
	defer k.mu.Unlock()

	server, err := k.serving()
	if err != nil {
		return err
	}

	if err := server.AskEach(request, each); err != nil {
		k.gone(err)

		return k.reach.named(err)
	}

	return nil
}

func (k *kept) serving() (*bridge.Server, error) {
	if k.server != nil {
		return k.server, nil
	}

	command, err := k.reach.Command(k.roots)
	if err != nil {
		return nil, k.reach.named(err)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		return nil, k.reach.named(err)
	}
	k.server = server

	return server, nil
}

func (k *kept) gone(err error) {
	if k.server == nil {
		return
	}
	fmt.Fprintf(os.Stderr, "the %s bridge went and a fresh one answers from here: %v\n", k.held.Label, err)
	k.server.Close()
	k.server = nil
}

func (k *kept) Close() error {
	k.mu.Lock()
	defer k.mu.Unlock()

	if k.server == nil {
		return nil
	}
	server := k.server
	k.server = nil

	return server.Close()
}

// process is a bridge that runs as a process, as a run asks it: every ask goes to the warm bridge a session keeps up for this project, and to one started for this run when there is none.
type process struct {
	held    Bridge
	reach   Process
	roots   []string
	own     *bridge.Server
	missed  sync.Once
	reached sync.Once
}

func (p *process) sayWarmBridgeUsed() {
	if !cli.Timed() {
		return
	}

	p.reached.Do(func() {
		fmt.Fprintf(os.Stderr, "· the %s bridge answered from the service this session keeps up\n", p.held.Kind)
	})
}

func (p *process) sayWarmBridgeMissed(err error) {
	p.missed.Do(func() {
		fmt.Fprintf(os.Stderr, "⚠ the %s bridge this session keeps up could not answer, so this run starts its own and pays its start-up: %v\n", p.held.Kind, err)
	})
}

const sharedAsk = 25

func shares(request bridge.Request) bool {
	return len(request.Write) <= sharedAsk
}

func (p *process) AskTallied(request bridge.Request, tally func()) (*contract.Stream, error) {
	if server, kept := p.warm(request); kept {
		defer server.Close()

		stream, err := server.AskTallied(request, tally)
		if err == nil {
			p.sayWarmBridgeUsed()

			return stream, nil
		}

		p.sayWarmBridgeMissed(err)
	}

	server, err := p.start()
	if err != nil {
		return nil, err
	}

	stream, err := server.AskTallied(request, tally)

	return stream, p.reach.named(err)
}

func (p *process) warm(request bridge.Request) (*bridge.Server, bool) {
	if !shares(request) {
		return nil, false
	}

	return keptFor(p.held.Kind, p.roots)
}

func (p *process) AskEach(request bridge.Request, each func(line contract.Line, raw []byte) error) error {
	if server, kept := p.warm(request); kept {
		defer server.Close()

		return p.reach.named(server.AskEach(request, each))
	}

	server, err := p.start()
	if err != nil {
		return err
	}

	return p.reach.named(server.AskEach(request, each))
}

func (p *process) start() (*bridge.Server, error) {
	if p.own != nil {
		return p.own, nil
	}

	command, err := p.reach.Command(p.roots)
	if err != nil {
		return nil, p.reach.named(err)
	}

	if notice := p.held.Notice(); notice != "" {
		fmt.Fprintf(os.Stderr, "⚠ %s\n", notice)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		return nil, p.reach.named(err)
	}
	p.own = server

	return server, nil
}

func (p *process) Close() error {
	if p.own == nil {
		return nil
	}

	return p.own.Close()
}

var Bridges = []Bridge{PHPBridge, Frontend, CSharp, Python, GoBridge}

var PHPBridge = Bridge{Kind: "php", Word: "php", Languages: []source.Language{source.PHP}, Label: "PHP",
	Reach:  Process{Command: func([]string) ([]string, error) { return php.Here().Cached().Command() }},
	Notice: nothingToSay,
}

var Frontend = Bridge{Kind: "frontend", Word: "frontend", Languages: []source.Language{source.Vue, source.TypeScript}, Label: "Vue and TypeScript",
	Reach:  Process{Command: func([]string) ([]string, error) { return frontend.Here().Cached().Command() }},
	Notice: nothingToSay,
}

var CSharp = Bridge{Kind: "roslyn", Word: "csharp", Languages: []source.Language{source.CSharp}, Label: "C#",
	Projects:   bridge.CSharpProjects,
	InOwnOrder: true,
	Reach:      Process{Command: func(roots []string) ([]string, error) { return bridge.Roslyn(roots...) }, Named: bridge.RoslynFailure},
	Notice:     bridge.RoslynNotice,
}

var GoBridge = Bridge{Kind: "go", Word: "go", Languages: []source.Language{source.Go}, Label: "Go",
	InOwnOrder: true,
	Reach:      InProcess{Stream: gotree.Stream},
	Notice:     nothingToSay,
}

var Python = Bridge{Kind: "mypy", Word: "python", Languages: []source.Language{source.Python}, Label: "Python",
	Reach:  Process{Command: func([]string) ([]string, error) { return bridge.Mypy() }},
	Notice: nothingToSay,
}

func nothingToSay() string {
	return ""
}

func Named(kind string) (Bridge, bool) {
	for _, held := range Bridges {
		if held.Kind == kind {
			return held, true
		}
	}

	return Bridge{}, false
}

var read = func(b Bridge, roots, files, written []string, tally func()) (*contract.Stream, error) {
	return b.ask(resolved(roots), resolved(files), resolved(written), tally)
}

func (b Bridge) Read(roots, files []string, tally func()) (*contract.Stream, error) {
	return b.readHalving(roots, files, files, tally, halvingsAtMost)
}

const halvingsAtMost = 8

// ReadShared serves every file whose kept line still stands from the one cache every checkout shares, and has the
// bridge read the rest, in as many shares as processes side by side; one process, or a single file, is one read.
func (b Bridge) ReadShared(roots, files []string, tally func(), processes int) (*contract.Stream, error) {
	kept := linesKeptFor(b, resolved(roots))
	hits, header, misses := kept.split(resolved(files))
	for range hits {
		tally()
	}
	if len(misses) == 0 {
		return &contract.Stream{Header: kept.standing(*header, resolved(roots)), Files: hits, Program: kept.program(nil),
			Trailer: contract.Trailer{Files: len(hits)}}, nil
	}

	stream, err := b.readMisses(roots, files, misses, tally, processes)
	if stream == nil || len(hits) == 0 {
		return stream, err
	}
	stream.Files = append(hits, stream.Files...)
	stream.Trailer.Files = len(stream.Files)
	stream.Program = kept.program(stream.Program)

	return stream, err
}

func (b Bridge) readMisses(roots, files, misses []string, tally func(), processes int) (*contract.Stream, error) {
	if processes <= 1 || len(misses) < 2 {
		return b.readHalving(roots, files, misses, tally, halvingsAtMost)
	}

	files = misses
	shares := min(processes, len(files))
	streams := make([]*contract.Stream, shares)
	failures := make([]error, shares)
	var reading sync.WaitGroup
	for share := range shares {
		reading.Add(1)
		go func() {
			defer reading.Done()
			from, to := share*len(files)/shares, (share+1)*len(files)/shares
			streams[share], failures[share] = b.readHalving(roots, files, files[from:to], tally, halvingsAtMost)
		}()
	}
	reading.Wait()

	var whole *contract.Stream
	for _, stream := range streams {
		whole = joined(whole, stream)
	}

	return onceEach(whole), errors.Join(failures...)
}

// onceEach keeps one line per file: a share writes the files it was asked for and reads others beside them as
// context, so a file one share wrote and another read is kept as written.
func onceEach(stream *contract.Stream) *contract.Stream {
	if stream == nil {
		return nil
	}

	at := map[string]int{}
	var kept []*contract.File
	for _, file := range stream.Files {
		earlier, met := at[file.Path]
		switch {
		case !met:
			at[file.Path] = len(kept)
			kept = append(kept, file)
		case kept[earlier].Context && !file.Context:
			kept[earlier] = file
		}
	}
	stream.Files, stream.Trailer.Files = kept, len(kept)

	return stream
}

// readHalving reads the files and writes those asked; when that fails it retries each half of what it writes, read
// on its own, so one file the bridge cannot read costs only itself.
func (b Bridge) readHalving(roots, files, written []string, tally func(), halvings int) (*contract.Stream, error) {
	stream, err := read(b, roots, files, written, tally)
	if err == nil || halvings <= 0 || len(written) < 2 || isUnavailable(err) {
		return stream, err
	}

	first, second := written[:len(written)/2], written[len(written)/2:]
	read, firstErr := b.readHalving(roots, first, first, tally, halvings-1)
	rest, secondErr := b.readHalving(roots, second, second, tally, halvings-1)

	if firstErr != nil && secondErr != nil {
		return stream, err
	}

	return joined(read, rest), errors.Join(firstErr, secondErr)
}

func (b Bridge) ask(roots, files, written []string, tally func()) (*contract.Stream, error) {
	asker, err := b.Reach.Open(b, roots)
	if err != nil {
		return nil, err
	}
	defer asker.Close()

	kept := linesKeptFor(b, roots)
	stream := &contract.Stream{}
	err = asker.AskEach(bridge.AskedOf(b.Projects, files, written).Request(), func(line contract.Line, raw []byte) error {
		switch {
		case line.Header != nil:
			stream.Header = *line.Header
		case line.File != nil:
			stream.Files = append(stream.Files, line.File)
			kept.keep(stream.Header, line.File, raw)
			if !line.File.Context {
				tally()
			}
		case line.Program != nil:
			stream.Program = kept.program(line.Program)
		case line.Trailer != nil:
			stream.Trailer = *line.Trailer
		}

		return nil
	})
	if err != nil && stream.Header.Contract == "" {
		return nil, err
	}

	return stream, err
}

func joined(read, rest *contract.Stream) *contract.Stream {
	switch {
	case read == nil:
		return rest
	case rest == nil:
		return read
	}

	read.Files = append(read.Files, rest.Files...)
	read.Trailer.Files = len(read.Files)

	return read
}

func (b Bridge) Request(files []string) bridge.Request {
	return bridge.AskedOf(b.Projects, files, files).Request()
}
