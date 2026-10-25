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
// languages it reads, the name a reader of a report sees, how it is started, and what it must be told once where
// starting it says something a person should know.
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
	Start      func(roots []string) ([]string, error)
	Notice     func() string
}

// Bridges are the bridges a scan reads, in the order the PHP tool read them.
var Bridges = []Bridge{PHPBridge, Frontend, CSharp, Python}

// PHPBridge is the PHP bridge, php-parser, kept up as the plugin's php service.
var PHPBridge = Bridge{Kind: "php", Word: "php", Languages: []source.Language{source.PHP}, Label: "PHP",
	Start:  func([]string) ([]string, error) { return php.Here().Cached().Command() },
	Notice: nothingToSay,
}

// Frontend is the bridge that reads Vue and TypeScript, one bridge for both because one parser reads both, kept up
// as the plugin's frontend service.
var Frontend = Bridge{Kind: "frontend", Word: "frontend", Languages: []source.Language{source.Vue, source.TypeScript}, Label: "Vue and TypeScript",
	Start:  func([]string) ([]string, error) { return frontend.Here().Cached().Command() },
	Notice: nothingToSay,
}

// CSharp is the C# bridge, Roslyn, kept up as the plugin's roslyn service.
var CSharp = Bridge{Kind: "roslyn", Word: "csharp", Languages: []source.Language{source.CSharp}, Label: "C#",
	WholeRoots: true,
	InOwnOrder: true,
	Start:      func(roots []string) ([]string, error) { return bridge.Roslyn(roots...) },
	Notice:     bridge.RoslynNotice,
}

// Python is the Python bridge, mypy, kept up as the plugin's mypy service.
var Python = Bridge{Kind: "mypy", Word: "python", Languages: []source.Language{source.Python}, Label: "Python",
	Start:  func([]string) ([]string, error) { return bridge.Mypy() },
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

// read is the funnel's own body: ask for the warm bridge, else start one for this run.
func (b Bridge) read(roots, files []string, tally func()) (*contract.Stream, error) {
	roots, files = resolved(roots), resolved(files)

	if server, kept := keptFor(b.Kind, roots); kept {
		defer server.Close()

		if stream, err := server.AskTallied(b.request(roots, files), tally); err == nil {
			return stream, nil
		}
	}

	command, err := b.Start(roots)
	if err != nil {
		return nil, b.failed(err)
	}

	if notice := b.Notice(); notice != "" {
		fmt.Fprintf(os.Stderr, "⚠ %s\n", notice)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		return nil, b.failed(err)
	}
	defer server.Close()

	stream, err := server.AskTallied(b.request(roots, files), tally)
	if err != nil {
		return nil, b.failed(err)
	}

	return stream, nil
}

// request is what the bridge is asked: the files to write, and the roots as well for a bridge that compiles
// everything under them so each type resolves.
func (b Bridge) request(roots, files []string) bridge.Request {
	if b.WholeRoots {
		return bridge.Request{Paths: roots, Write: files}
	}

	return bridge.Request{Paths: files, Write: files}
}

// failed names the bridge in what went wrong, since a reader of the report is told which language went unread.
func (b Bridge) failed(err error) error {
	if b.Kind == CSharp.Kind {
		return bridge.RoslynFailure(err)
	}

	return err
}
