package hooks

import (
	"bufio"
	"bytes"
	"errors"
	"net"
	"os"
	"path/filepath"
	"slices"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/engine"
)

// Judging answers a run that asks the service to judge for it, so the judging is done against the tree the
// service already holds instead of one the run reads for itself. A terminal run is handed no socket and no data
// folder, so the name of this socket is worked out from the project's path, as a language bridge's is.
//
// It is the same saving the per-file mark gets, for the command: reading a tree costs 25 seconds for 1518 files
// and 55 for 5286, and a judging that reads nothing pays the fill pass and the rules alone.
func Judging(root string) (net.Listener, bool) {
	path := warm.Socket(root)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		return nil, false
	}

	os.Chmod(path, 0o600)

	return listener, true
}

// serveJudging answers each run that asks, until the listener closes.
func serveJudging(listener net.Listener, root string) {
	for {
		connection, err := listener.Accept()
		if err != nil {
			if errors.Is(err, net.ErrClosed) {
				return
			}

			continue
		}

		go answerJudging(connection, root)
	}
}

// answerJudging reads one request and answers it.
func answerJudging(connection net.Conn, root string) {
	defer connection.Close()

	_ = connection.SetDeadline(time.Now().Add(10 * time.Minute))

	line, err := bufio.NewReader(connection).ReadBytes('\n')
	if err != nil && len(line) == 0 {
		return
	}

	request, asked := warm.RequestOf(line)
	if !asked {
		return
	}

	connection.Write(warm.Answered(judgedFor(request, root)))
}

// judgedFor is what the judging the request asks for came to. The run's own folder is what the project, the config
// and the scope are read from, never the service's, whose folder is the plugin's; the codebase is the tree this
// service holds, where it holds one that covers what the run reads.
func judgedFor(request warm.Request, root string) warm.Answer {
	var out, errs bytes.Buffer

	// A judging the base cannot cover is DECLINED rather than read here. Reading a tree inside this process
	// costs 17 to 55 seconds of it, and this process answers every hook of the session: a worker that ran a
	// judging of its own worktree, whose files no base holds, made two later edits of its own wait two minutes
	// behind that read. The run pays the read in its own process instead, where it blocks nobody.
	declined := false

	code, err := (judge.Command{
		In:      request.In,
		Reading: heldOrDeclined(root, request.In, &declined),
	}).Run(cli.InputOf("judge", request.Judge...), cli.Console{Out: &out, Err: &errs})

	if declined {
		return warm.Answer{Declined: true}
	}

	if err != nil {
		return warm.Answer{Out: out.String(), Err: errs.String() + err.Error() + "\n", Code: 1}
	}

	return warm.Answer{Out: out.String(), Err: errs.String(), Code: code}
}

// spliced is how many files the service reads again for one judging. What it reads it reads in the process every
// hook of the session is answered by, so it reads in front of every edit. Up to this many the read is the files
// the judging differs by, which is seconds; past it the change is checkout-sized, the base it would be spliced
// into is half a tree out of date, and the run reads the tree in its own process where it blocks nobody.
const spliced = 200

// heldOrDeclined is how the service reads a codebase for a run: the tree it holds for the branch, with the files
// that differ from it read again. A judging the base cannot cover is declined, and says so through the flag, so
// the run reads it in its own process rather than in the one every hook of the session is answered by.
//
// A worker's judging is answered the same way, which is most of why this exists. A worktree's files are not the
// base's, so asking the base whether it holds them answered no every time: every worker's hand-in read the tree
// from scratch, 17 to 55 seconds, and started a language bridge of its own beside the warm one, which is what
// still cost an edit 6 to 12 seconds of parsing while three hand-ins ran. What a worktree differs by git says
// exactly, and warm.Base.ViewFrom already splices those files in over the project's own by the path each has
// under its own checkout.
func heldOrDeclined(root, in string, declined *bool) func(scan.Sources) (*engine.Codebase, error) {
	return func(sources scan.Sources) (*engine.Codebase, error) {
		named, base, held := baseFor(root)
		if !held {
			*declined = true

			return engine.Load(), nil
		}

		// Every language's files, because Files with none named answers none: asking it that way made this
		// decline every run and, before that, made every run read the tree here rather than use the base.
		asked := sources.Files(source.Languages...)
		if len(asked) == 0 {
			*declined = true

			return engine.Load(), nil
		}

		// The checkout the run reads, which for a worker is its own worktree. A run pointed at a tree outside
		// it, another project beside this one or a calibration over a consumer's sources, reaches this service
		// too, since a run finds the service from the folder it was started in; this base has nothing to say
		// about those files.
		checkout := checkoutOf(named.project, in)
		for _, walked := range sources.Roots() {
			if _, inside := warm.Under(checkout, walked); !inside {
				*declined = true

				return engine.Load(), nil
			}
		}

		read, covered := differingFrom(base, named, checkout, asked)
		if !covered || len(read) > spliced {
			*declined = true

			return engine.Load(), nil
		}

		return viewOf(base, named, checkout, read)
	}
}

// differingFrom are the files the service must read before the base answers, and whether it can answer at all:
// the base's own files whose content has moved since it read them, and, for a run in a worktree, every file that
// worktree differs from the branch the base was read at by, committed or not.
//
// The two are joined rather than one replacing the other. A worktree's file is a file of its OWN that no base
// ever read, so the base's fingerprints say nothing about it; and the main checkout drifts under the base on its
// own account, which is exactly what those fingerprints do say. A worktree whose branch shares no history with
// the base's cannot be answered from it, and saying nothing differs would be the worst answer of the three: a
// green tick over every sin the worker wrote.
func differingFrom(base *warm.Base, named branchRoot, checkout string, asked []string) ([]string, bool) {
	gone := base.Gone()

	// A file that is gone cannot be read again, only left out, so it never joins what is asked for.
	read := slices.DeleteFunc(base.Moved(), func(file string) bool { return slices.Contains(gone, file) })

	// A file the base never read is read now, whatever made it: an edit, a shell, a checkout, a rename. Left
	// out, it is judged as though it did not exist, which is the quietest wrong answer a judging can give. What
	// a checkout's file stands in for is what the base is asked about, so a worktree's thousand files are not
	// all "new" merely for lying under another root.
	for _, file := range asked {
		if _, known := base.Holds(counterpartIn(checkout, named.project, file)); !known {
			read = append(read, file)
		}
	}

	if checkout != named.project {
		changed, told := git.ChangedVsBranch(checkout, named.branch)
		if !told {
			return nil, false
		}

		for file := range changed {
			read = append(read, file)
		}
	}

	slices.Sort(read)

	return slices.Compact(read), true
}

// counterpartIn is the project's own file a checkout's file stands in for: the file itself in the project's own
// checkout, and the file at the same path under the project for a worktree's.
func counterpartIn(checkout, project, file string) string {
	if checkout == project {
		return file
	}

	under, inside := warm.Under(checkout, file)
	if !inside {
		return file
	}

	return filepath.Join(project, under)
}

// viewOf is the base with these files read again in place of its own, and without the ones that are gone, which
// is the tree a judging is answered against.
//
// Nothing is excluded from that read: these are the files we have WORKED OUT differ, and a worker's lie in a
// worktree under a folder the project excludes, so excluding again would read none of them. A file that is gone
// is left out rather than read, since nothing announces a shell's removals and a base that still holds one
// answers about code nobody has any more.
func viewOf(base *warm.Base, named branchRoot, checkout string, read []string) (*engine.Codebase, error) {
	gone := base.Gone()

	if len(read) == 0 && len(gone) == 0 {
		return base.Codebase(), nil
	}

	if len(read) == 0 {
		return base.ViewFrom(checkout, nil, named.project, gone), nil
	}

	rebuilt, err := scan.Walk(read, source.Excluded{}).Load()
	if err != nil || rebuilt == nil {
		return base.Codebase(), err
	}

	return base.ViewFrom(checkout, rebuilt.Streams(), named.project, gone), nil
}
