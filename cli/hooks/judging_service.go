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
	"github.com/jessegall/code-commandments/cli/ledger"
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

	if drop, lanes := lanesAsked(payloadOf(line)); lanes {
		connection.Write(lanesAnswered(drop))

		return
	}

	request, asked := warm.RequestOf(line)
	if !asked {
		return
	}

	connection.Write(warm.Answered(judgedFor(request, root)))
}

func judgedFor(request warm.Request, root string) warm.Answer {
	costs := ledger.Of(root)

	if why, coming := TheTreeIsComing(root, checkoutOf(root, request.In)); coming {
		costs.Counts(ledger.JudgingsToldTheTreeIsComing, 1, request.In)

		return warm.Answer{Declined: true, Coming: true, Because: string(why)}
	}

	waited := aSlotToJudgeIn()
	defer freeTheSlot()

	var out, errs bytes.Buffer

	declined := ""
	overlay := 0
	spent := judge.Spent{}

	code, err := (judge.Command{
		In:       request.In,
		Reading:  heldOrDeclined(root, request.In, &declined, &overlay),
		Spending: func(done judge.Spent) { spent = done },
	}).Run(cli.InputOf("judge", request.Judge...), cli.Console{Out: &out, Err: &errs})

	recordServed(root, request.In, declined, overlay, spent)

	if declined != "" {
		if pastWhatOneSpliceCarries(declined) {
			if smaller, held := fromOneFileRules(request, root, declined); held {
				costs.Counts(ledger.JudgingsAnsweredSmaller, 1, request.In+" because "+declined)
				smaller.Err += waited

				return smaller
			}
		}

		costs.Counts(ledger.JudgingsDeclined, 1, request.In+" because "+declined)

		return warm.Answer{Declined: true, Because: declined + waited}
	}

	costs.Counts(ledger.JudgingsFromTheTree, 1, request.In)

	if err != nil {
		return warm.Answer{Out: out.String(), Err: errs.String() + err.Error() + "\n" + waited, Code: 1}
	}

	return warm.Answer{Out: out.String(), Err: errs.String() + waited, Code: code}
}

func recordServed(root, in, declined string, overlay int, spent judge.Spent) {
	if !recorded(root) {
		return
	}

	checkout := checkoutOf(root, in)
	kind := "project"
	if checkout != strings.TrimRight(git.ProjectRoot(root), "/") {
		kind = "worktree"
	}

	Record(bookkeepingIn(root), Judged{
		At: time.Now(), Event: "judge", Checkout: checkout, Kind: kind, Branch: git.CurrentBranch(checkout),
		Held: declined == "", Why: Narrowed(declined), Overlay: overlay, Parsed: overlay,
		ReadMs: spent.ReadMs, RuleMs: spent.RuleMs, Asked: spent.Rules, Memory: MemoryNow(),
	})
}

var judgingSlots = make(chan struct{}, slotsToJudgeIn)

func aSlotToJudgeIn() string {
	select {
	case judgingSlots <- struct{}{}:
		return ""
	default:
	}

	started := time.Now()
	judgingSlots <- struct{}{}

	return fmt.Sprintf("· this judging waited %s for one of the %d the service runs at a time, so a worker per core "+
		"cannot read a project each\n", time.Since(started).Round(time.Millisecond), slotsToJudgeIn)
}

func freeTheSlot() {
	<-judgingSlots
}

const slotsToJudgeIn = 2

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
	if chosen := filesInOneGoChosen(); chosen > 0 {
		return chosen
	}

	return min(mostReadInOneGo, max(leastReadInOneGo, files/partOfTheTree))
}

func notThisCheckouts(walked, checkout, project string) string {
	if checkout == "" || checkout == "/" {
		return "which checkout " + walked + " belongs to could not be worked out, so no tree of ours can answer " +
			"about it: it is in no git repository this service holds"
	}

	if project != "" && !strings.HasPrefix(walked, strings.TrimRight(project, "/")) {
		return walked + " belongs to another project than the one this service holds (" + project +
			"), so this tree says nothing about it"
	}

	return walked + " lies outside " + checkout + ", so this tree says nothing about it"
}

func heldOrDeclined(root, in string, declined *string, overlay *int) func(scan.Sources) (*engine.Codebase, error) {
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
				*declined = notThisCheckouts(walked, checkout, named.project)

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

		*overlay = len(read)

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

		for _, file := range asked {
			if changed[file] {
				differs.Own = append(differs.Own, file)
			}
		}

		deleted, _ := git.DeletedVsBranch(checkout, named.branch)
		for _, under := range deleted {
			differs.Gone = append(differs.Gone, filepath.Join(named.project, under))
		}

		for file := range git.AddedVsHead(named.project) {
			differs.Gone = append(differs.Gone, file)
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

func noProjectAt(root string) bool {
	return root == "" || root == "/" || git.Root(root) == ""
}

func pastWhatOneSpliceCarries(declined string) bool {
	return strings.Contains(declined, "this service reads for one judging")
}

func fromOneFileRules(request warm.Request, root, declined string) (warm.Answer, bool) {
	var out, errs bytes.Buffer

	refused := ""

	code, err := (judge.Command{
		In:      request.In,
		Reading: oneFileAtATime(root, request.In, &refused),
	}).Run(cli.InputOf("judge", append(slices.Clone(request.Judge), "--one-file-rules")...),
		cli.Console{Out: &out, Err: &errs})

	if refused != "" || err != nil {
		return warm.Answer{}, false
	}

	said := errs.String() + "· " + declined + ", so these are the rules that answer from one file alone; the " +
		"rules that read across files were not asked\n"

	return warm.Answer{Out: out.String(), Err: said, Code: code}, true
}

func oneFileAtATime(root, in string, refused *string) func(scan.Sources) (*engine.Codebase, error) {
	return func(sources scan.Sources) (*engine.Codebase, error) {
		asked := sources.Files(source.Languages...)
		if len(asked) == 0 {
			*refused = "the run reads no file of any language the tree holds"

			return engine.Load(), nil
		}

		if reads := readsInOneGo(0); len(asked) > reads {
			*refused = fmt.Sprintf("%d files were changed, past the %d even one rule at a time reads", len(asked), reads)

			return engine.Load(), nil
		}

		rebuilt, err := scan.Walk(asked, source.Excluded{}).Load()

		var incomplete scan.Incomplete
		if errors.As(err, &incomplete) {
			err = nil
		}

		if err != nil || rebuilt == nil {
			*refused = "the files that changed could not be read"

			return engine.Load(), nil
		}

		return rebuilt, nil
	}
}
