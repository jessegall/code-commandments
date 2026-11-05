package hooks

import (
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// kept are the bases this process holds, one per branch. The service answers every hook of a session from one
// process, so a tree it read once serves every later edit; a one-shot run holds none and judges the edited file
// alone, which is what every run did before a base was ever held.
var kept = warm.Keep()

// reading are the branches whose base is being read right now, so a burst of edits starts one read and not one
// per edit.
var reading sync.Map

// drifted is how many files may have moved under a base before it is given up rather than patched. A few are an
// agent at work, and rebuilding them is what a view is for; hundreds are a checkout, a rebase or an install, and
// the tree they belong to is a different tree.
const drifted = 25

// AgainstTheProgram are the marks for one edited file judged against the whole program, through a view of its
// branch's base, and false where no base is held yet.
//
// This is what the base is for. Judged against the file alone, a rule that follows a chain of parents, a caller
// or a recurrence answers as though that file were the program, so those rules are kept out of a per-file mark
// (detectors.ReadsBeyondOneFile) and an edit is never told about them. Against a view of the base they answer
// about the real program, and the mark can carry every rule the project has enabled.
func AgainstTheProgram(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines, root string) ([]SinMark, time.Duration, bool) {
	held, base, ok := baseFor(root)
	if !ok {
		return nil, 0, false
	}

	// The checkout the file lies in, which for a worker is its own worktree: its files stand in for the
	// project's own at the same path under it, so the program holds one copy of each source, the worker's.
	checkout := strings.TrimRight(held.project, "/")
	if own := git.Root(file); own != "" {
		checkout = own
	}

	moved := base.Stale(file, time.Now())
	if len(moved) > drifted {
		kept.Drop(held.project, held.branch)

		return nil, 0, false
	}

	// An edit pays for the file it named and for no other. Every file that moved is worth knowing about, but
	// handing them all to a bridge here puts one edit's answer behind every file something else touched: a
	// worker with twenty changed files paid for twenty parses on whichever edit landed after a sweep, and a
	// parse of one file on a large tree is seconds. So the others give the base up instead, and the next read
	// takes them in together, in the background, where nobody waits on it.
	elsewhere := slices.DeleteFunc(slices.Clone(moved), func(path string) bool { return path == file })
	if len(elsewhere) > 0 {
		kept.Drop(held.project, held.branch)

		// And this edit is answered alone, not from the base it just gave up: that base holds the old tree of
		// every file that moved and still holds the ones that are gone, so the edit that NOTICES the drift was
		// the one answered from the drifted tree.
		return nil, 0, false
	}

	view := base.Codebase()
	var read time.Duration

	if slices.Contains(moved, file) {
		// Parsed when the event arrived, together with every other file waiting then, since a bridge's cost is
		// per ask and not per file. Where nothing parsed it ahead, or it moved again since, it is parsed here as
		// every edit was before.
		//
		// Nothing is excluded either way: this is the file we were TOLD changed, and it has already been held to
		// what the project judges. A worker's file lies in a worktree under a folder the project excludes, so
		// excluding again would read none of it.
		reading := time.Now()

		// What the file is NOW, taken before it is read: marked afterwards, a file written again during the
		// read would be held as current when the tree holds the version before it.
		mark := warm.Mark(file)

		streams, already := parsed.streamsOf(file)

		if !already {
			rebuilt, err := scan.OneFile(file).Load()
			if err != nil || rebuilt == nil {
				return nil, time.Since(reading), false
			}

			streams = rebuilt.Streams()
		}

		read = time.Since(reading)

		if checkout == strings.TrimRight(held.project, "/") {
			// No files are dropped here: a file gone anywhere but the one named has already given the base up
			// above, and the named file is one we were told about and have just read.
			view = base.ViewFrom(checkout, streams, held.project, nil)

			// And the view becomes the base, so the next edit is judged against this file as it now is rather
			// than as the base first read it.
			kept.Patched(base, view, map[string]string{file: mark})
		} else {
			// A worktree cannot patch the project's base, since its content belongs to another checkout, so it
			// keeps an overlay of its OWN: every file that worktree differs from the branch by, held together,
			// so this edit is judged beside the worker's earlier work rather than beside the project's copies
			// of the files the worker has already changed.
			view = overlaid(kept.Over(checkout), base, held, file, streams, mark, time.Now())
		}
	}

	// A file the view does not hold cannot be judged against the program, and answering "no sins" for it would
	// be the worst answer of the three: a worker's new file in its own worktree got exactly that, no mark and no
	// answer at all, while a judging of it found eight sins. Judged alone it is answered as every edit was
	// before a tree was held.
	if !holds(view, file) {
		return nil, read, false
	}

	return marksOver(view, marking, file, rules, changed), read, true
}

// overlaid is the base seen through this checkout's own overlay, with this edit's file taken into it.
//
// An overlay new to a checkout is filled with everything that checkout differs by, in one ask: an edit is never
// the first thing a worker did, and a bridge's cost is per ask and not per file, so reading all of them costs
// what reading one costs. What it no longer has is asked of git, at most once every Sweep, since that is a
// process and the answer changes only when the worker commits or removes something.
func overlaid(over *warm.Overlay, base *warm.Base, held branchRoot, file string, streams []*contract.Stream, mark string, now time.Time) *engine.Codebase {
	if over.Empty() {
		fill(over, held, file)
	}

	if over.SweepDue(now) {
		over.Removed(removedIn(held, over.Checkout))
	}

	over.Patch(streams, map[string]string{file: mark})

	return base.ViewFrom(over.Checkout, over.Streams(), held.project, over.Gone())
}

// fill reads everything the checkout differs from its branch by, less the file this edit already read.
func fill(over *warm.Overlay, held branchRoot, file string) {
	changed, told := git.ChangedVsBranch(over.Checkout, held.branch)
	if !told {
		return
	}

	var reading []string
	for path := range changed {
		if path != file {
			reading = append(reading, path)
		}
	}

	if len(reading) == 0 {
		return
	}

	taken := warm.Taken(reading)

	// Nothing is excluded: a worktree lies under a folder the project excludes, so excluding again would read
	// none of it.
	rebuilt, err := scan.Walk(reading, source.Excluded{}).Load()
	if err != nil || rebuilt == nil {
		return
	}

	over.Patch(rebuilt.Streams(), taken)
}

// removedIn are the project's files the checkout no longer has: what its branch removed since it left the base's,
// named at the path the base holds them at.
func removedIn(held branchRoot, checkout string) []string {
	deleted, told := git.DeletedVsBranch(checkout, held.branch)
	if !told {
		return nil
	}

	var gone []string
	for _, under := range deleted {
		gone = append(gone, filepath.Join(held.project, under))
	}

	return gone
}

// holds says whether the codebase read that file.
func holds(codebase *engine.Codebase, file string) bool {
	for _, held := range codebase.Files() {
		if held.Path == file {
			return true
		}
	}

	return false
}

// marksOver are the marks the rules find in that one file of the codebase. Every rule is asked, since the
// codebase is the whole program; only a finding IN the edited file is this edit's mark.
//
// Each rule is asked about the edited file alone (Reporting), so its query walks that file's nodes rather than
// every file's while its checks still read the whole program. A rule that weighs the candidates of the whole
// program to reach its verdict is asked about everything, since its answer about this file is decided by every
// other candidate.
func marksOver(codebase *engine.Codebase, marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) []SinMark {
	narrowed := codebase.Reporting(file)

	var marks []SinMark

	for _, rule := range rules {
		asked := narrowed
		if _, weighs := rule.(detectors.Aggregating); weighs {
			asked = codebase
		}

		for _, match := range safely(rule, asked) {
			if match.File() != file {
				continue
			}

			marks = append(marks, marking.Of(rule, match, changed))
		}
	}

	return marks
}

// serving says whether this process is the service, which is the only process a base is worth reading for: a
// run started for one hook answers and ends, so a tree it began reading would cost a worker's CPU for a quarter
// of a minute and be thrown away unused.
var serving bool

// Serves says this process is the service, so a base read for one hook serves every later one.
func Serves() {
	serving = true
}

// Warming reads a branch's base in the background, unless one is held or a read is already running. The hook
// that asks for it is answered from the edited file alone, as every hook was; the next one is answered from the
// program.
func Warming(root string, project config.Config) {
	if !serving {
		return
	}

	named, ok := rootOf(root)
	if !ok {
		return
	}

	if _, held := kept.Holds(named.project, named.branch); held {
		return
	}

	if _, already := reading.LoadOrStore(named.key(), true); already {
		return
	}

	go func() {
		defer reading.Delete(named.key())

		roots, err := config.DeclaredRoots(named.project)
		if err != nil || len(roots) == 0 {
			roots = []string{named.project}
		}

		sources := scan.Walk(roots, source.Under(named.project, project.Excluded)).Only(reads(named.project, project)...)

		// What every file is before the read, which is what the base is marked with: the read takes 25 to 55
		// seconds, and a file written during it must read as moved afterwards rather than as the tree's own.
		taken := warm.Taken(sources.Files(source.Languages...))

		codebase, err := sources.Load()
		if err != nil || codebase == nil {
			return
		}

		kept.Hold(named.project, named.branch, roots, codebase, taken)
	}()
}

// reads are the languages the project writes, less C#, whose bridge compiles whole units of its own and is kept
// warm as a service rather than held here.
func reads(root string, project config.Config) []source.Language {
	var languages []source.Language
	for _, language := range source.Languages {
		if language != source.CSharp && project.Reads(root, language) {
			languages = append(languages, language)
		}
	}

	return languages
}

// branchRoot is the base a checkout answers from: the project's main checkout and the branch it is on.
type branchRoot struct {
	project string
	branch  string
}

// key names the base, for the one read at a time this holds.
func (b branchRoot) key() string {
	return b.project + "\x00" + b.branch
}

// rootOf is the base a path answers from. A worktree has no base of its own: it answers from the branch the
// MAIN checkout is on, which is the branch the main agent works on whether that is called main or development,
// and every worktree cut from it shares that one tree.
func rootOf(path string) (branchRoot, bool) {
	project := git.ProjectRoot(path)
	if project == "" {
		return branchRoot{}, false
	}

	branch := git.CurrentBranch(project)
	if branch == "" {
		return branchRoot{}, false
	}

	// A detached HEAD is no branch, whatever git calls it. Taken as one, every detached commit of a project
	// shares one base, and a worktree answered from it is diffed against the literal name HEAD, which resolves
	// to the WORKTREE's own head: the merge-base is then the worker's own tip, so its committed work reads as no
	// difference at all and its judging is a green tick over everything it wrote. A mid-rebase checkout is
	// detached, which is an ordinary thing for the main checkout to be.
	if branch == "HEAD" {
		return branchRoot{}, false
	}

	return branchRoot{project: project, branch: branch}, true
}

// baseFor is the base the path answers from, with what names it.
func baseFor(path string) (branchRoot, *warm.Base, bool) {
	named, ok := rootOf(path)
	if !ok {
		return branchRoot{}, nil, false
	}

	base, held := kept.Holds(named.project, named.branch)

	return named, base, held
}
