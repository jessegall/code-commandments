package hooks

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// mergeEvent is the event the journal holds just before a worker's branch is taken in, and mergedEvent the one it
// sends once it landed.
const (
	mergeEvent  = "worker_merging"
	mergedEvent = "worker_merge_result"
)

// mergeSinsShown is how many sins a held merge names before it counts the rest: the reason is read in a line of
// the chat, and the branch is still there to judge in full.
const mergeSinsShown = 5

// MergeHeld is the answer to a worker's merge: nothing when its changes are clean, and a refusal naming the sins
// when they are not, which holds the merge and leaves the worker's proofs and task as they were.
//
// Only the worker's own files are judged, read from the event, and only by the rules that judge a file on its own.
// The window the journal holds the merge in is measured in seconds, so a whole-tree judge cannot answer in it; and
// a worker's work IS its changed files, which is what a reviewer would read.
func MergeHeld(line map[string]any) string {
	worktree := asText(line["worktree"])
	if worktree == "" {
		return ""
	}

	marks := mergeSins(worktree, mergedFiles(line, worktree))
	if len(marks) == 0 {
		return ""
	}

	refusal := mergeReason(worker4Of(line), marks)

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

// mergedFiles are the files the worker changed, as paths on disk inside its worktree. A workspace of repositories
// names each inner path with its repository folder, which is how the worktree holds them too.
func mergedFiles(line map[string]any, worktree string) []string {
	var files []string
	listed, _ := line["files"].([]any)

	for _, each := range listed {
		if named := asText(each); named != "" {
			files = append(files, filepath.Join(worktree, named))
		}
	}

	return files
}

// mergeSins are the sins the files hold, judged in one read of them all: a worker that changed twenty files is one
// scan, not twenty.
func mergeSins(worktree string, files []string) []SinMark {
	if len(files) == 0 {
		return nil
	}

	project, err := config.Load(worktree)
	if err != nil {
		return nil
	}

	judged := slices.DeleteFunc(files, func(file string) bool { return !project.Writes(source.OfFile(file)) })
	if len(judged) == 0 {
		return nil
	}

	enabled, err := project.Enabled(config.InstalledIn(worktree))
	if err != nil {
		return nil
	}

	enabled = append(enabled, custom.Load(worktree).Enabled(project)...)
	single := slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile)

	codebase, err := scan.Walk(judged, source.Excluded{}).Load()
	if err != nil {
		return nil
	}

	return marksOf(codebase, single)
}

// marksOf is every sin the rules find in the codebase, as marks of the moment: none of them is touched, since a
// merge is not an edit — what matters is that the file holds the sin at all.
func marksOf(codebase *engine.Codebase, rules []detectors.Detector) []SinMark {
	var marks []SinMark

	for _, rule := range rules {
		for _, match := range safely(rule, codebase) {
			marks = append(marks, MarkOf(rule, match, git.ChangedLines{}))
		}
	}

	return marks
}

// mergeReason is why the merge is held: how many sins, and the first of them named where they are, each as the
// worker's branch holds it.
func mergeReason(worker string, marks []SinMark) string {
	said := []string{worker + " carries " + natural.Counted(len(marks), "sin") + ":"}

	for _, mark := range marks[:min(len(marks), mergeSinsShown)] {
		said = append(said, "  "+mark.Found())
	}

	if more := len(marks) - mergeSinsShown; more > 0 {
		said = append(said, "  and "+natural.Counted(more, "sin")+" more")
	}

	return strings.Join(said, "\n")
}

// worker4Of is the worker the event is about, as a line of the chat would name it.
func worker4Of(line map[string]any) string {
	name := asText(line["name"])
	if number := asText(line["worker"]); name == "" && number != "" {
		return "worker " + number
	}

	if name == "" {
		return "the worker's branch"
	}

	return name
}
