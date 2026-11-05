package hooks

import (
	"bufio"
	"bytes"
	"errors"
	"net"
	"os"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
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
		Reading: heldOrDeclined(root, &declined),
	}).Run(cli.InputOf("judge", request.Judge...), cli.Console{Out: &out, Err: &errs})

	if declined {
		return warm.Answer{Declined: true}
	}

	if err != nil {
		return warm.Answer{Out: out.String(), Err: errs.String() + err.Error() + "\n", Code: 1}
	}

	return warm.Answer{Out: out.String(), Err: errs.String(), Code: code}
}

// heldOrDeclined is how the service reads a codebase for a run: the tree it holds for the branch, with the files
// that have moved since read again. A run about files no base holds is declined, and says so through the flag, so
// the run reads them in its own process rather than in the one every hook of the session is answered by.
func heldOrDeclined(root string, declined *bool) func(scan.Sources) (*engine.Codebase, error) {
	return func(sources scan.Sources) (*engine.Codebase, error) {
		named, base, ok := baseFor(root)

		// Every language's files, because Files with none named answers none: asking it that way made this
		// decline every run and, before that, made every run read the tree here rather than use the base.
		asked := sources.Files(source.Languages...)

		if !ok || len(asked) == 0 {
			*declined = true

			return engine.Load(), nil
		}

		for _, file := range asked {
			if _, known := base.Holds(file); !known {
				*declined = true

				return engine.Load(), nil
			}
		}

		project, err := config.Load(named.project)
		if err != nil {
			*declined = true

			return engine.Load(), nil
		}

		return viewOf(base, named, project, asked)
	}
}

// viewOf is the base with every file that has moved read again, which is the tree a judging is answered against.
func viewOf(base *warm.Base, named branchRoot, project config.Config, asked []string) (*engine.Codebase, error) {
	var moved []string
	for _, file := range asked {
		if unchanged, known := base.Holds(file); known && !unchanged {
			moved = append(moved, file)
		}
	}

	if len(moved) == 0 {
		return base.Codebase(), nil
	}

	rebuilt, err := scan.Walk(moved, source.Under(named.project, project.Excluded)).Load()
	if err != nil || rebuilt == nil {
		return base.Codebase(), err
	}

	return base.View(rebuilt.Streams(), named.project), nil
}
