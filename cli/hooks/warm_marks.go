package hooks

import (
	"sync"
	"sync/atomic"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const (
	longestTreeRead  = 10 * time.Minute
	treeReadLookedAt = 250 * time.Millisecond
)

var kept = keeping()

func keeping() *warm.Keeper {
	held := warm.Keep()
	held.Leaving(func(checkout string) { parsed.leaves(checkout) })

	return held
}

var reading sync.Map

var lastRead sync.Map

// Narrowed is why a judging could not put the project's whole set of rules to a file: empty where it could. The
// rules that read across files cannot answer from one file alone, so a narrowed judging is a smaller answer, and
// an answer nobody is told is smaller reads as a clean file.
type Narrowed string

const (
	noTreeHeld     Narrowed = "no tree of this checkout is held yet, and one is being read now"
	cannotList     Narrowed = "git could not say what this checkout differs from its branch by"
	fillingFailed  Narrowed = "what this checkout differs from its branch by could not be read"
	checkoutGone   Narrowed = "this checkout is no longer on disk"
	noTreeComing   Narrowed = "no tree of this checkout is held and none is being read, so only the rules that answer from one file will ever run here"
	filesNotOnDisk Narrowed = "what changed is not on disk"
	noneToJudge    Narrowed = "nothing of this edit is a file this project judges"
	tooMuchMoved   Narrowed = "more files changed than one view can carry, so the tree was let go"
	patchFailed    Narrowed = "the held tree could not be brought up to what changed"
	parseFailed    Narrowed = "what changed could not be parsed"
	notInTheTree   Narrowed = "what changed is not in the tree that is held"
	pastTheCeiling Narrowed = "the service already holds as much of this project as it is allowed to"
	judgedAlone    Narrowed = "an edit is judged on its own, and the whole tree is read only to judge the changes together"
)

func marksOver(codebase *engine.Codebase, marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) []SinMark {
	narrowed := codebase.Reporting(file)
	language := source.OfFile(file)

	var marks []SinMark

	for _, rule := range rules {
		if !detectors.JudgesAny(rule, language.Engines()) {
			continue
		}

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

var serving atomic.Bool

func reads(root string, project config.Config) []source.Language {
	var languages []source.Language
	for _, language := range source.Languages {
		if !scan.ReadInUnits(language) && project.Reads(root, language) {
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

func rootOf(path string) (branchRoot, bool) {
	project := git.ProjectRoot(path)
	if project == "" {
		return branchRoot{}, false
	}

	branch := git.CurrentBranch(project)
	if branch == "" {
		return branchRoot{}, false
	}

	if branch == "HEAD" {
		return branchRoot{}, false
	}

	return branchRoot{project: project, branch: branch}, true
}

func seenFor(path string) (branchRoot, *warm.Base, bool) {
	return lookedUp(path, kept.Sees)
}

func lookedUp(path string, look func(project, branch string) (*warm.Base, bool)) (branchRoot, *warm.Base, bool) {
	named, ok := rootOf(path)
	if !ok {
		return branchRoot{}, nil, false
	}

	base, held := look(named.project, named.branch)

	return named, base, held
}

func cutOf(event Event) string {
	if cut := event.Cut(); cut != "" {
		return cut
	}

	return git.CurrentBranch(checkoutOf(event.Root, event.Root))
}

// treeRead is one read of a whole tree while it runs: what it reads, since when, and how many of its files are read so far.
type treeRead struct {
	project string
	branch  string
	began   time.Time
	done    atomic.Int64
	total   atomic.Int64
}

const (
	leastReadInOneGo = 200

	mostReadInOneGo = 1000

	partOfTheTree = 4
)

var letGo sync.Map
