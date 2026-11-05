package hooks

import (
	"sync"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
)

// A file an edit changed has to be parsed before any rule can read it, and that parse is what an edit actually
// pays: 93 per cent of a judging, measured here by the stress harness. The bridge's cost is per ASK and not per
// file — one ask of nine files costs 349ms against 343 for one — so nine edits parsing one file each pay that
// whole cost nine times for work that fits in one ask.
//
// So a file is parsed when its event ARRIVES rather than when its turn in the queue comes, and every file waiting
// at that moment goes over in ONE ask. One event is still judged at a time, which is what keeps two checks of one
// file from running at once; what leaves the queue is the parse, which is nearly all of it.
//
// Nothing here can give a wrong answer: a parse is held with what the file was when it was read, so a file that
// moved again since is parsed anew, and a file nothing parsed ahead is parsed by the judging itself, exactly as
// every edit did before.
var parsed = keepParsing()

// read is one file as a bridge last wrote it, with what the file was when it was read.
type read struct {
	mark   string
	stream *contract.Stream
}

// parsing holds the files this process has parsed ahead of their judging, and the ones waiting to be.
type parsing struct {
	mu      sync.Mutex
	held    map[string]read
	waiting map[string]bool
	wake    chan struct{}
	// reading is how a group of files is read: the streams a bridge wrote for them. A test replaces it to count
	// the asks.
	reading func(files []string) ([]*contract.Stream, error)
	// ahead says a file is parsed when its event arrives rather than when its turn comes. It is how the saving
	// is measured: off, every edit parses its own file in the queue, which is what every edit did before.
	ahead bool
}

// keepParsing is the parser of this process, its goroutine started.
func keepParsing() *parsing {
	p := &parsing{
		held:    map[string]read{},
		waiting: map[string]bool{},
		wake:    make(chan struct{}, 1),
		reading: readTogether,
		ahead:   true,
	}

	go p.work()

	return p
}

// readTogether is one ask for every file of the group. Nothing is excluded: these are the files we were TOLD
// changed, and a worker's lies in a worktree under a folder the project excludes, so excluding again would read
// none of them.
func readTogether(files []string) ([]*contract.Stream, error) {
	codebase, err := scan.Walk(files, source.Excluded{}).Load()
	if err != nil || codebase == nil {
		return nil, err
	}

	return codebase.Streams(), nil
}

// expecting parses the file a moment names before that moment is judged, so the judging finds it parsed and
// spends the rules alone. It is called on the connection's own goroutine, after the answer has gone back, so no
// hook ever waits on it.
func expecting(given map[string]any) {
	if !serving || !parsed.ahead {
		return
	}

	moment := MomentOf(given)
	if moment.File == nil {
		return
	}

	root := workspace.ProjectRoot(moment.Cwd)

	// The project's own configuration, never a worktree's copy: what is judged is the project's answer, and the
	// checkout is only the root its folders join to.
	project, err := config.Load(projectOf(root))
	if err != nil {
		return
	}

	if file := judgedFile(root, *moment.File, project); file != "" {
		parsed.expect(file)
	}
}

// expect puts the file among those waiting to be parsed and wakes the parser.
func (p *parsing) expect(file string) {
	p.mu.Lock()
	p.waiting[file] = true
	p.mu.Unlock()

	select {
	case p.wake <- struct{}{}:
	default:
	}
}

// work parses what is waiting, a checkout at a time, until nothing is.
func (p *parsing) work() {
	for range p.wake {
		for {
			group := p.take()
			if len(group) == 0 {
				break
			}

			p.fill(group)
		}
	}
}

// take is every waiting file of ONE checkout. Two checkouts are never asked about together: a worker's
// src/order.py and the project's own are the same module to a type checker, and a module graph cannot hold two
// files of one module. A second checkout's files are taken on the next turn, which is one more ask and not one
// per file.
func (p *parsing) take() []string {
	p.mu.Lock()
	defer p.mu.Unlock()

	checkout := ""

	var group []string
	for file := range p.waiting {
		own := git.Root(file)
		if checkout == "" {
			checkout = own
		}

		if own != checkout {
			continue
		}

		group = append(group, file)
		delete(p.waiting, file)
	}

	return group
}

// fill parses the group in one ask and holds each file as the bridge wrote it. What each file WAS is taken before
// the ask, so a file written again while the ask ran is held as moved and parsed anew by whoever asks for it.
func (p *parsing) fill(group []string) {
	marks := map[string]string{}
	for _, file := range group {
		marks[file] = warm.Mark(file)
	}

	streams, err := p.reading(group)
	if err != nil {
		return
	}

	p.mu.Lock()
	defer p.mu.Unlock()

	for _, stream := range streams {
		for _, file := range stream.Files {
			// One file's own stream, so a judging splices in the file it is about and no other: the files of a
			// group are several agents' edits, and a view holding all of them would answer about every one.
			alone := *stream
			alone.Files = []*contract.File{file}

			p.held[file.Path] = read{mark: marks[file.Path], stream: &alone}
		}
	}
}

// streamsOf are the file's streams as this process already parsed them, and false where it has not, or where the
// file has moved since. A parse handed over is let go of: it is read once, by the judging it was read for, and a
// later question about the same file is a question about a file that may have moved again.
func (p *parsing) streamsOf(file string) ([]*contract.Stream, bool) {
	p.mu.Lock()
	defer p.mu.Unlock()

	held, known := p.held[file]
	if !known {
		return nil, false
	}

	delete(p.held, file)

	if held.mark != warm.Mark(file) {
		return nil, false
	}

	return []*contract.Stream{held.stream}, true
}
