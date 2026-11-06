package hooks

import (
	"bufio"
	"bytes"
	"errors"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/engine"
)

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

func judgedFor(request warm.Request, root string) warm.Answer {
	var out, errs bytes.Buffer

	declined := ""

	code, err := (judge.Command{
		In:      request.In,
		Reading: heldOrDeclined(root, request.In, &declined),
	}).Run(cli.InputOf("judge", request.Judge...), cli.Console{Out: &out, Err: &errs})

	if declined != "" {
		return warm.Answer{Declined: true, Because: declined}
	}

	if err != nil {
		return warm.Answer{Out: out.String(), Err: errs.String() + err.Error() + "\n", Code: 1}
	}

	return warm.Answer{Out: out.String(), Err: errs.String(), Code: code}
}

func holdsTheLanguageOf(base *warm.Base, file string) bool {
	for _, held := range base.Languages() {
		if named, known := source.Of(held); known && source.OfFile(file) == named {
			return true
		}
	}

	return false
}

const (
	leastReadInOneGo = 200

	mostReadInOneGo = 1000

	partOfTheTree = 4
)

func readsInOneGo(files int) int {
	return min(mostReadInOneGo, max(leastReadInOneGo, files/partOfTheTree))
}

func heldOrDeclined(root, in string, declined *string) func(scan.Sources) (*engine.Codebase, error) {
	return func(sources scan.Sources) (*engine.Codebase, error) {
		checkout := checkoutOf(root, in)

		named, base, why := answering(root, checkout)
		if why != "" {
			*declined = string(why)
			if said, tried := howTheReadWent(named); tried {
				*declined += ": " + said
			}

			return engine.Load(), nil
		}

		asked := sources.Files(source.Languages...)
		if len(asked) == 0 {
			*declined = "the run reads no file of any language the tree holds"

			return engine.Load(), nil
		}

		for _, walked := range sources.Roots() {
			if _, inside := warm.Under(checkout, walked); !inside {
				*declined = walked + " lies outside " + checkout + ", so this tree says nothing about it"

				return engine.Load(), nil
			}
		}

		differs, covered := differingFrom(base, named, checkout, asked)
		if !covered {
			*declined = checkout + " shares no history with the branch the tree was read at, " + named.branch

			return engine.Load(), nil
		}

		read, gone := differs.read(), differs.Gone
		if reads := readsInOneGo(base.Files()); len(read) > reads {
			*declined = fmt.Sprintf("%d files differ from the tree, past the %d this service reads for one judging"+
				" (%s)", len(read), reads, differs.where())

			return engine.Load(), nil
		}

		return viewOf(base, named, checkout, read, gone, declined)
	}
}

func differingFrom(base *warm.Base, named branchRoot, checkout string, asked []string) (Differs, bool) {
	differs := Differs{Gone: base.Gone()}

	differs.Moved = slices.DeleteFunc(base.Moved(), func(file string) bool {
		return slices.Contains(differs.Gone, file)
	})

	differs.Asked = map[string]string{}
	differs.Tree = fmt.Sprintf("the tree holds %d files of %v, read over %v", base.Files(), base.Languages(), base.Roots())

	for _, file := range asked {
		if !holdsTheLanguageOf(base, file) {
			continue
		}

		stands := warm.Counterpart(checkout, named.project, file)
		if _, known := base.Holds(stands); !known {
			differs.Unread = append(differs.Unread, file)
			differs.Asked[file] = stands
		}
	}

	if checkout != named.project {
		changed, told := git.ChangedVsBranch(checkout, named.branch)
		if !told {
			return Differs{}, false
		}

		for file := range changed {
			differs.Own = append(differs.Own, file)
		}

		deleted, _ := git.DeletedVsBranch(checkout, named.branch)
		for _, under := range deleted {
			differs.Gone = append(differs.Gone, filepath.Join(named.project, under))
		}
	}

	return differs, true
}

// Differs are the files a judging differs from the tree by, kept APART by what made each one differ: the base's own files whose bytes have moved since it read them, the files the run's walk found that the base never read at all, and, for a run in a worktree, the files that worktree differs from the base's branch by.
type Differs struct {
	Moved  []string
	Unread []string
	Own    []string
	Gone   []string

	Asked map[string]string

	Tree string
}

func (d Differs) read() []string {
	read := slices.Concat(d.Moved, d.Unread, d.Own)
	slices.Sort(read)

	return slices.Compact(read)
}

func (d Differs) where() string {
	groups := []struct {
		named string
		files []string
	}{
		{"the tree's own files whose bytes moved", d.Moved},
		{"files the walk found that the tree never read", d.Unread},
		{"files this checkout differs from the tree's branch by", d.Own},
	}

	var said []string
	largest := 0

	for at, group := range groups {
		said = append(said, fmt.Sprintf("%d %s", len(group.files), group.named))
		if len(group.files) > len(groups[largest].files) {
			largest = at
		}
	}

	return strings.Join(said, ", ") + "; " + firstFew(groups[largest].files, d.Asked) + "; " + d.Tree
}

const shown = 8

func firstFew(files []string, asked map[string]string) string {
	named := slices.Clone(files)
	slices.Sort(named)

	if len(named) == 0 {
		return "none to name"
	}

	more := ""
	if len(named) > shown {
		more = fmt.Sprintf(" and %d more", len(named)-shown)
		named = named[:shown]
	}

	var said []string
	for _, file := range named {
		if stands, mapped := asked[file]; mapped && stands != file {
			said = append(said, file+" (asked about "+stands+")")

			continue
		}

		said = append(said, file)
	}

	return "they are " + strings.Join(said, ", ") + more
}

func viewOf(base *warm.Base, named branchRoot, checkout string, read, gone []string, declined *string) (*engine.Codebase, error) {

	if len(read) == 0 && len(gone) == 0 {
		return base.Codebase(), nil
	}

	if len(read) == 0 {
		return base.ViewFrom(checkout, nil, named.project, gone), nil
	}

	rebuilt, err := scan.Walk(read, source.Excluded{}).Load()

	var incomplete scan.Incomplete
	if errors.As(err, &incomplete) {
		err = nil
	}

	if err != nil || rebuilt == nil {
		*declined = fmt.Sprintf("the %d files that differ could not be read: %v", len(read), err)

		return engine.Load(), nil
	}

	return base.ViewFrom(checkout, rebuilt.Streams(), named.project, gone), nil
}
