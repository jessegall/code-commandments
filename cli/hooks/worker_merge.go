package hooks

import (
	"maps"
	"os"
	"slices"
	"strings"
	"time"

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

// wholeTreeWindow is the window a judge of the whole worktree needs: it is read once with every rule, the bridge
// of each of its languages started, and that is tens of seconds on a tree of any size.
//
// changedFilesWindow is what the shallow judge needs instead: the changed files alone, read by the rules that need
// no more than one file.
//
// A window short of both judges nothing. The journal cuts a hold off at its window and records the fault, and a
// faulting plugin is switched off, so no sin is worth answering late for: the branch is still there to judge, and
// the agent's own hooks judge every file it writes.
//
// The manifest declares ten seconds today, and the journal sends the window it cut ours to, so the shallow judge
// is what a merge really gets; the whole tree waits on raising that declaration, which waits in turn on the
// journal release that lets a merge hold take longer than a tool call.
const (
	wholeTreeWindow    = time.Minute
	changedFilesWindow = 5 * time.Second
)

// defaultBase is the branch a worker's work is measured against when nothing names one: the same default
// `judge --branch` has.
const defaultBase = "main"

// MergeHeld is the answer to a worker's merge: nothing when its changes are clean, and a refusal naming the sins
// when they are not, which holds the merge and leaves the worker's proofs and task as they were.
//
// Only the lines the worker changed are reported, whatever the depth: a worker answers for its own work, and the
// sins a file already carried are the project's.
func MergeHeld(line map[string]any) string {
	merge := mergeOf(line)

	marks := merge.sins()
	if len(marks) == 0 {
		return ""
	}

	refusal := mergeReason(merge.worker, marks)

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

// merging is a worker's merge as the event describes it: who it is, where its work stands, what its branch was cut
// from, the files it names, and how long the journal waits for this answer.
type merging struct {
	worker   string
	worktree string
	base     string
	files    []string
	window   time.Duration
}

// mergeOf is the merge the event describes.
func mergeOf(line map[string]any) merging {
	fields := eventFields(line)

	return merging{
		worker:   worker4Of(fields),
		worktree: asText(fields["worktree"]),
		base:     asText(fields["base"]),
		files:    namedFiles(fields["files"]),
		window:   windowOf(fields["window_ms"]),
	}
}

// eventFields are the event's own fields. The journal sends them under `data`, and a line that carries them at its
// top level is read too, which is how a journal that knew no `data` sent them.
func eventFields(line map[string]any) map[string]any {
	fields := map[string]any{}
	for name, value := range line {
		fields[name] = value
	}

	data, held := line["data"].(map[string]any)
	if !held {
		return fields
	}

	for name, value := range data {
		fields[name] = value
	}

	return fields
}

// windowOf is how long the journal waits for this answer, as the event names it in milliseconds; nothing where it
// names none, which is a journal that does not say, and then nothing is judged.
func windowOf(named any) time.Duration {
	switch window := named.(type) {
	case float64:
		return time.Duration(window) * time.Millisecond
	case int:
		return time.Duration(window) * time.Millisecond
	}

	return 0
}

// namedFiles are the files the event names, as the worktree holds them: a workspace of repositories names each
// inner path with its repository folder, which is how the worktree holds them too.
func namedFiles(named any) []string {
	listed, _ := named.([]any)
	var files []string

	for _, each := range listed {
		if name := asText(each); name != "" {
			files = append(files, name)
		}
	}

	return files
}

// sins are the sins the worker's own lines carry, judged as deeply as the window allows.
func (m merging) sins() []SinMark {
	if m.worktree == "" {
		return nil
	}

	project, err := config.Load(m.worktree)
	if err != nil {
		return nil
	}

	rules, err := project.Enabled(config.InstalledIn(m.worktree))
	if err != nil {
		return nil
	}
	rules = append(rules, custom.Load(m.worktree).Enabled(project)...)

	switch {
	case m.window >= wholeTreeWindow:
		return m.deep(project, rules)
	case m.window >= changedFilesWindow:
		return m.shallow(project, rules)
	}

	return nil
}

// deep judges the branch as `judge --branch=<base>` judges it: the whole worktree is read, so a rule that spans
// files has the context it needs, and every rule runs over it.
func (m merging) deep(project config.Config, rules []detectors.Detector) []SinMark {
	changed := m.changed(project)
	if len(changed) == 0 {
		return nil
	}

	codebase, err := scan.Walk([]string{m.worktree}, source.Under(m.worktree, project.Excluded)).Load()
	if err != nil {
		return m.shallow(project, rules)
	}

	return m.marks(codebase, rules, changed)
}

// shallow judges the changed files alone, by the rules that need no more than one file: what fits a window of
// seconds, and what a reviewer of the branch would read first.
func (m merging) shallow(project config.Config, rules []detectors.Detector) []SinMark {
	changed := m.changed(project)
	if len(changed) == 0 {
		return nil
	}

	codebase, err := scan.Walk(slices.Sorted(maps.Keys(changed)), source.Excluded{}).Load()
	if err != nil {
		return nil
	}

	return m.marks(codebase, slices.DeleteFunc(rules, detectors.ReadsBeyondOneFile), changed)
}

// changed are the judged files the worker changed, as paths on disk: the ones the event names, and otherwise what
// its branch changed since it left its base, committed or not.
func (m merging) changed(project config.Config) map[string]bool {
	listed := git.Listed(m.worktree, m.files)
	if len(m.files) == 0 {
		listed, _ = git.ChangedVsBranch(m.worktree, m.cutFrom())
	}

	for file := range listed {
		if !project.Writes(source.OfFile(file)) {
			delete(listed, file)
		}
	}

	return listed
}

// cutFrom is what the worker's work is measured against: the base the event names, which is the branch its
// environment was on, and otherwise the branch the main checkout has now, since a worker is cut from that branch
// and not from main.
func (m merging) cutFrom() string {
	if m.base != "" {
		return m.base
	}

	if branch := git.CurrentBranch(os.Getenv("JOURNAL_ROOT")); branch != "" {
		return branch
	}

	return defaultBase
}

// marks are the rules' findings that stand on a line the worker changed, each a mark of the moment: none of them
// is touched in the sense an edit is, since a merge is not an edit — what matters is that the branch holds it.
func (m merging) marks(codebase *engine.Codebase, rules []detectors.Detector, changed map[string]bool) []SinMark {
	since := git.MergeBase(m.worktree, m.cutFrom())
	lines := map[string]git.ChangedLines{}
	var marks []SinMark

	for _, rule := range rules {
		for _, match := range safely(rule, codebase) {
			if !changed[match.File()] {
				continue
			}
			if _, read := lines[match.File()]; !read {
				lines[match.File()] = git.ChangedLinesSince(m.worktree, since, match.File())
			}
			if lines[match.File()].Covers(match.Line()) {
				marks = append(marks, MarkOf(rule, match, lines[match.File()]))
			}
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
func worker4Of(fields map[string]any) string {
	name := asText(fields["name"])
	if number := asText(fields["worker"]); name == "" && number != "" {
		return "worker " + number
	}

	if name == "" {
		return "the worker's branch"
	}

	return name
}
