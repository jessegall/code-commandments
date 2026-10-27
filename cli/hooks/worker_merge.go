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
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// The moments a worker's work is held at, and the event the journal sends once a merge landed.
//
// deliveringEvent is the one the plugin subscribes to: the work is committed, rebased onto the project's branch and
// its own tests have passed, and it has not yet reached the main agent, so a refusal there is refused to the
// WORKER, which fixes what it wrote, runs its tests again and comes back. Only clean work reaches the agent that
// asked for it.
//
// The asking is after the worker's tests on purpose. A worker whose tests are still failing has a fault to fix
// first, and holding its work over a sin before it even runs green would tell it two things at once.
//
// mergeEvent is the same reading at the later moment, when the work is taken in, and NO journal raises it any more:
// the manifest stopped subscribing to it on the user's ruling that the judging belongs at clock-out alone, since a
// merge paid for it under load. What that gives up is the one thing the later moment could see and the earlier one
// could not: a branch that sat while the project moved, whose lines meet files that have changed under them, so a
// rule reading across files is answered against where the branch is going rather than where it was written. The
// reading is kept here because putting it back is one line of the manifest.
const (
	deliveringEvent = "worker_delivering"
	mergeEvent      = "worker_merging"
	mergedEvent     = "worker_merge_result"
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
// The manifest asks for three minutes, which is what a journal from 4.61.0 on caps a merge hold at
// (workers.merge_window_seconds); it sends the window it cut ours to as the event's `window_ms` and the branch the
// work merges into as its `base`, and it asks about at most workers.merge_limit merges at once, the queue's own
// wait not counted against the window. An older journal cuts every hold to its general ceiling of fifty
// milliseconds and names no window at all, so a merge there is judged not at all, which is the honest answer when
// the journal gave us no time to answer in.
const (
	wholeTreeWindow    = time.Minute
	changedFilesWindow = 5 * time.Second
)

// MergeHeld is the answer to a worker's merge: nothing when its changes are clean, and a refusal naming the sins
// when they are not, which holds the merge and leaves the worker's proofs and task as they were.
//
// Only the lines the worker changed are reported, whatever the depth: a worker answers for its own work, and the
// sins a file already carried are the project's.
func MergeHeld(line map[string]any) string {
	return workHeld(line)
}

// DeliveryHeld is the answer to a worker delivering its work, which is the same question asked earlier: the work
// is committed, rebased and green, and has not yet reached the main agent, so a refusal is refused to the worker,
// which fixes what it wrote and comes back rather than leaving it for whoever merges.
//
// It is the same reading as the merge's. Nothing about which sins a worker answers for changes with the moment it
// is asked, so there is one reading of that and two doors to it.
func DeliveryHeld(line map[string]any) string {
	return workHeld(line)
}

// workHeld is the answer to either moment: nothing when the worker's own lines are clean, and a refusal naming
// the sins when they are not.
func workHeld(line map[string]any) string {
	work := mergeOf(line)

	marks := work.sinsWithin()
	if len(marks) == 0 {
		return ""
	}

	refusal := mergeReason(work.worker, marks)

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

// sinsWithin is what the judge found, and nothing where it did not answer in time. The window is a deadline and
// not only a permission: nobody here knows how large the worktree is, and a tree that outlasts even a generous
// window would answer late, which is the fault that costs the plugin itself. A judge still running when the
// deadline passes is left to finish into nothing and the merge is let through.
func (m merging) sinsWithin() []SinMark {
	answered := make(chan []SinMark, 1)
	go func() { answered <- m.sins() }()

	select {
	case marks := <-answered:
		return marks
	case <-time.After(m.deadline()):
		return nil
	}
}

// deadline is how much of the window the judge is given: all but a tenth of it, so the answer is written and read
// inside the window too.
func (m merging) deadline() time.Duration {
	return m.window - max(m.window/10, 200*time.Millisecond)
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
	project = project.Judging(rules)

	switch {
	case m.window >= wholeTreeWindow:
		return m.deep(project, rules)
	case m.window >= changedFilesWindow:
		return m.shallow(project, rules)
	}

	return nil
}

// deep judges the branch as `judge --branch=<base>` judges it: the folders the project declares are read whole, so
// a rule that spans files has the context it needs, and every rule runs over it.
func (m merging) deep(project config.Config, rules []detectors.Detector) []SinMark {
	changed := m.changed(project)
	if len(changed) == 0 {
		return nil
	}

	codebase, err := scan.Walk(m.roots(project), source.Under(m.worktree, project.Excluded)).Load()
	if err != nil {
		return m.shallow(project, rules)
	}

	return m.marks(codebase, rules, changed)
}

// roots are the folders a judge of this project reads, resolved inside the worktree: the ones the project
// declares, and the worktree itself only for a project that declares none.
//
// It reads what `judge --branch` reads and nothing more. A walk of the worktree's own root is not the same thing:
// the journal links a worker's library folders into its worktree, a .venv and a node_modules pointing at the main
// checkout's, so the root holds every library the project never judges. Reading them cost agent-journal 115.6s
// against 17.7s for the same branch judged by its declared folders, and a merge there waited minutes.
func (m merging) roots(project config.Config) []string {
	if len(project.Paths) == 0 {
		return []string{m.worktree}
	}

	return config.Absolute(m.worktree, project.Paths)
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
//
// A merge is judged by the same configuration a judge of the whole tree is, which is the one question
// `config.IsJudged` answers: the language the project writes, the folders it declares, and the folders it leaves
// out. A hold that read a file no judge of the project would ever read holds a merge over a sin the project never
// asked about, which is what a worker's branch being refused over its own test file showed.
func (m merging) changed(project config.Config) map[string]bool {
	listed := git.Listed(m.worktree, m.files)
	if len(m.files) == 0 {
		listed, _ = git.ChangedVsBranch(m.worktree, m.cutFrom())
	}

	for file := range listed {
		if !project.IsJudged(m.worktree, file) {
			delete(listed, file)
		}
	}

	return listed
}

// cutFrom is what the worker's work is measured against: the base the event names, which is the branch its
// environment was on; otherwise the branch the main checkout has now, since a worker is cut from that branch and
// not from the trunk; and otherwise what the project declares its trunk to be, which is the same answer a bare
// `judge --branch` takes.
func (m merging) cutFrom() string {
	if m.base != "" {
		return m.base
	}

	if branch := git.CurrentBranch(os.Getenv("JOURNAL_ROOT")); branch != "" {
		return branch
	}

	return scope.BaseOf(m.worktree)
}

// marks are the rules' findings that stand on a line the worker changed, each a mark of the moment: none of them
// is touched in the sense an edit is, since a merge is not an edit — what matters is that the branch holds it.
func (m merging) marks(codebase *engine.Codebase, rules []detectors.Detector, changed map[string]bool) []SinMark {
	since := git.MergeBase(m.worktree, m.cutFrom())
	marking := Marks(m.worktree)
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
			if !lines[match.File()].Covers(match.Line()) {
				continue
			}

			// A sin this project has reported is not the worker's to answer for, and holding a merge over a claim
			// a maintainer is already answering would hold it for as long as the answer takes.
			if mark := marking.Of(rule, match, lines[match.File()]); !mark.IsFiled() {
				marks = append(marks, mark)
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
