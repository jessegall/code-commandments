package hooks

import (
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/detectors"
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
func AgainstTheProgram(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines, root string, project config.Config) ([]SinMark, bool) {
	held, base, ok := baseFor(root)
	if !ok {
		return nil, false
	}

	moved := base.Stale(file, time.Now())
	if len(moved) > drifted {
		kept.Drop(held.project, held.branch)

		return nil, false
	}

	view := base.Codebase()
	if len(moved) > 0 {
		rebuilt, err := scan.Walk(moved, source.Under(held.project, project.Excluded)).Load()
		if err != nil || rebuilt == nil {
			return nil, false
		}

		view = base.View(rebuilt.Streams(), held.project)
	}

	return marksOver(view, marking, file, rules, changed), true
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

		codebase, err := scan.Walk(roots, source.Under(named.project, project.Excluded)).Only(reads(named.project, project)...).Load()
		if err != nil || codebase == nil {
			return
		}

		kept.Hold(named.project, named.branch, roots, codebase)
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
