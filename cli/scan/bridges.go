package scan

import (
	"fmt"
	"os"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

// Bridge is one language's bridge: the name its socket and its service carry, the word its has- verb uses, the
// languages it reads, the name a reader of a report sees, how it is reached, and what it must be told once where
// reaching it says something a person should know.
//
// Every bridge is reached the same way, by Read below. A bridge that had a path of its own is how three of these
// four came to pay a process's start-up on every run while one did not.
type Bridge struct {
	Kind      string
	Word      string
	Languages []source.Language
	Label     string
	// WholeRoots says whether the bridge is asked for the roots and told which files to write, rather than asked
	// for the files alone: the C# bridge compiles every project under the roots so each type resolves.
	WholeRoots bool
	// InOwnOrder says whether the bridge's stream already stands in the order a walk of the tree gives.
	InOwnOrder bool
	// Reach is how this bridge is reached, and the one thing that differs between a bridge that runs as a process
	// and a language this binary parses itself.
	Reach  Reach
	Notice func() string
}

// Reach is how a bridge is reached. A run asks it with Open and takes whatever answers; a service asks it with
// Own for a bridge of its own to keep up, and is told when the language needs none.
type Reach interface {
	// Open answers the bridge a run asks: for a bridge that runs as a process, the warm one a session keeps up
	// for this project when there is one, and one for this run alone when there is not.
	Open(held Bridge, roots []string) (bridge.Asker, error)
	// Own answers a bridge of this process's own, never a warm one, which is what a service keeps up. False says
	// the language needs no process, so no service is kept for it and no socket is dialled for it either.
	Own(held Bridge, roots []string) (bridge.Asker, bool, error)
}

// Process is the reach of a bridge that runs as a process: the command that starts it, and, for a bridge whose
// absence a person must be told about by name, how its failures are named.
type Process struct {
	Command func(roots []string) ([]string, error)
	Named   func(err error) error
}

// Open is the bridge a run asks: one asker that goes to the warm bridge for each ask and starts one of its own
// when there is none, or when the warm one could not answer.
func (p Process) Open(held Bridge, roots []string) (bridge.Asker, error) {
	return &process{held: held, reach: p, roots: roots}, nil
}

// Own is a bridge of this process's own, which is what a service keeps up: never the warm one, since a service
// that found a socket would be answering through another service for the same project.
func (p Process) Own(held Bridge, roots []string) (bridge.Asker, bool, error) {
	command, err := p.Command(roots)
	if err != nil {
		return nil, true, p.named(err)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		return nil, true, p.named(err)
	}

	return server, true, nil
}

// named names the bridge's part in what went wrong, for a bridge that says so by name.
func (p Process) named(err error) error {
	if err == nil || p.Named == nil {
		return err
	}

	return p.Named(err)
}

// process is a bridge that runs as a process, as a run asks it: every ask goes to the warm bridge a session keeps
// up for this project, and to one started for this run when there is none. The run's own is started once and let
// go of when the run closes the asker.
//
// A run never leaves a bridge behind. The warm ones belong to the journal's plugin, which starts them with the
// session and stops them with it; a run of the tool that daemonised one of its own would orphan it.
type process struct {
	held  Bridge
	reach Process
	roots []string
	own   *bridge.Server
}

// AskTallied is the whole stream, from the warm bridge when it answers and from one of this run's own when it
// does not: a warm bridge that fails an ask is a bridge gone, not an answer.
func (p *process) AskTallied(request bridge.Request, tally func()) (*contract.Stream, error) {
	if server, kept := keptFor(p.held.Kind, p.roots); kept {
		defer server.Close()

		if stream, err := server.AskTallied(request, tally); err == nil {
			return stream, nil
		}
	}

	server, err := p.start()
	if err != nil {
		return nil, err
	}

	stream, err := server.AskTallied(request, tally)

	return stream, p.reach.named(err)
}

// AskEach hands each line of the answer on as it arrives. It takes no second ask, since the lines already handed
// over cannot be handed over again: a reader that wants another try asks again itself.
func (p *process) AskEach(request bridge.Request, each func(line contract.Line, raw []byte) error) error {
	if server, kept := keptFor(p.held.Kind, p.roots); kept {
		defer server.Close()

		return p.reach.named(server.AskEach(request, each))
	}

	server, err := p.start()
	if err != nil {
		return err
	}

	return p.reach.named(server.AskEach(request, each))
}

// start is the bridge this run holds of its own, started once.
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

// Close lets go of the bridge this run started, if it started one.
func (p *process) Close() error {
	if p.own == nil {
		return nil
	}

	return p.own.Close()
}

// Bridges are the bridges a scan reads, in the order the PHP tool read them.
var Bridges = []Bridge{PHPBridge, Frontend, CSharp, Python}

// PHPBridge is the PHP bridge, php-parser, kept up as the plugin's php service.
var PHPBridge = Bridge{Kind: "php", Word: "php", Languages: []source.Language{source.PHP}, Label: "PHP",
	Reach:  Process{Command: func([]string) ([]string, error) { return php.Here().Cached().Command() }},
	Notice: nothingToSay,
}

// Frontend is the bridge that reads Vue and TypeScript, one bridge for both because one parser reads both, kept up
// as the plugin's frontend service.
var Frontend = Bridge{Kind: "frontend", Word: "frontend", Languages: []source.Language{source.Vue, source.TypeScript}, Label: "Vue and TypeScript",
	Reach:  Process{Command: func([]string) ([]string, error) { return frontend.Here().Cached().Command() }},
	Notice: nothingToSay,
}

// CSharp is the C# bridge, Roslyn, kept up as the plugin's roslyn service.
var CSharp = Bridge{Kind: "roslyn", Word: "csharp", Languages: []source.Language{source.CSharp}, Label: "C#",
	WholeRoots: true,
	InOwnOrder: true,
	Reach:      Process{Command: func(roots []string) ([]string, error) { return bridge.Roslyn(roots...) }, Named: bridge.RoslynFailure},
	Notice:     bridge.RoslynNotice,
}

// Python is the Python bridge, mypy, kept up as the plugin's mypy service.
var Python = Bridge{Kind: "mypy", Word: "python", Languages: []source.Language{source.Python}, Label: "Python",
	Reach:  Process{Command: func([]string) ([]string, error) { return bridge.Mypy() }},
	Notice: nothingToSay,
}

// nothingToSay is the notice of a bridge whose starting says nothing a person needs to hear.
func nothingToSay() string {
	return ""
}

// Named is the bridge of that kind, which is how a command that keeps one up finds it; false for a kind no scan
// reads.
func Named(kind string) (Bridge, bool) {
	for _, held := range Bridges {
		if held.Kind == kind {
			return held, true
		}
	}

	return Bridge{}, false
}

// read is how every bridge is reached, whichever language it reads. It is a variable so a test can answer for a
// bridge without starting one; nothing else replaces it, and there is one of it rather than one per language.
var read = func(b Bridge, roots, files []string, tally func()) (*contract.Stream, error) {
	return b.read(roots, files, tally)
}

// Read is the one way a bridge is reached, whichever language it reads: the warm bridge a session keeps for this
// project when every root lies inside it, and otherwise one started for this run and closed when the run is done.
//
// A command never leaves a bridge behind. The warm ones belong to the journal's plugin, which starts them with the
// session and stops them with it; a run of the tool that daemonised one of its own would orphan it.
func (b Bridge) Read(roots, files []string, tally func()) (*contract.Stream, error) {
	return read(b, roots, files, tally)
}

// read is the funnel's own body: open the bridge, however it is reached, and ask it.
func (b Bridge) read(roots, files []string, tally func()) (*contract.Stream, error) {
	roots, files = resolved(roots), resolved(files)

	asker, err := b.Reach.Open(b, roots)
	if err != nil {
		return nil, err
	}
	defer asker.Close()

	return asker.AskTallied(b.Request(roots, files), tally)
}

// Request is what the bridge is asked: the files to write, and the roots as well for a bridge that compiles
// everything under them so each type resolves. A service warming its own bridge asks exactly this, so what it
// loads is what a run will ask for.
func (b Bridge) Request(roots, files []string) bridge.Request {
	if b.WholeRoots {
		return bridge.Request{Paths: roots, Write: files}
	}

	return bridge.Request{Paths: files, Write: files}
}
