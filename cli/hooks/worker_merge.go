package hooks

import (
	"encoding/json"
	"maps"
	"os"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const (
	deliveringEvent = "worker_delivering"
	mergeEvent      = "worker_merging"
	mergedEvent     = "worker_merge_result"
)

const (
	wholeTreeWindow    = time.Minute
	changedFilesWindow = 5 * time.Second
	DeclaredWindow     = 180 * time.Second
)

func MergeHeld(line map[string]any) string {
	return workHeld(line)
}

func DeliveryHeld(line map[string]any) string {
	return workHeld(line)
}

func workHeld(line map[string]any) string {
	work := mergeOf(line)
	if !work.judgedByRole() {
		return ""
	}

	return work.answered()
}

func (work merging) keeps(said string) {
	project := projectOf(work.worktree)
	if project == "" {
		return
	}

	space := workspace.At(project, "")

	MergesIn(space).Keeps(firstText(work.base, work.worker, "a hand-in"), work.worktree, said)

	if !strings.Contains(said, "was not judged in full") {
		return
	}

	held := dashboard.Unjudgeable(space)
	if len(held) >= mergesKept {
		held = held[len(held)-mergesKept+1:]
	}

	_ = dashboard.KeepsUnjudgeable(space, append(held, said))

	if recorded(project) {
		Record(bookkeepingIn(project), Judged{
			At: time.Now(), Event: deliveringEvent, Kind: "worktree", Checkout: work.worktree,
			Why: Narrowed(string(gotNowhere)), Said: "the hand-in",
		})
	}
}

func (work merging) answered() string {
	found := work.sinsWithin()

	if found.got != gotWhole {
		said := Unjudged(work.worker+"'s hand-in was not judged in full", string(found.got),
			binary.Judging(work.worktree)+" --changes")

		if len(found.marks) > 0 {
			said = mergeTold(work.worker, "", work.worktree, found.marks) + "\n\n" + said
		}

		work.keeps(said)

		return (JournalAnswer{Whisper: &said}).Line4()
	}

	if len(found.marks) == 0 {
		return ""
	}

	refusal := mergeReason(work.worker, found.marks, work.worktree)

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

// handIn is what a hand-in's judging came to: the marks it found, and how far it got, which a slice of marks alone cannot say.
type handIn struct {
	marks []SinMark
	got   gotTo
}

type gotTo string

const (
	gotNowhere gotTo = "nothing of this hand-in was judged: the time the journal allows ran out before even the files it changed could be read"
	gotPartly  gotTo = "only the files this hand-in changed were judged, and the rules that read across files were not asked, because the time the journal allows ran out"
	gotWhole   gotTo = ""
)

func (m merging) sinsWithin() handIn {
	within := time.After(m.deadline())

	shallow := make(chan []SinMark, 1)
	go func() { shallow <- m.shallowly() }()

	var held []SinMark

	select {
	case marks := <-shallow:
		held = marks
	case <-within:
		return handIn{got: gotNowhere}
	}

	answered := make(chan []SinMark, 1)
	go func() { answered <- m.sins() }()

	select {
	case marks := <-answered:
		return handIn{marks: marks, got: gotWhole}
	case <-within:
		go m.tellsLater(answered)

		return handIn{marks: held, got: gotPartly}
	}
}

func (m merging) tellsLater(answered chan []SinMark) {
	marks, came := <-answered
	if !came || len(marks) == 0 {
		return
	}

	said := mergeTold(m.worker, " (judged after the journal stopped waiting)", m.worktree, marks)

	m.keeps(said)

	queue, queued := Queue4In(os.Getenv(pluginData))
	if !queued {
		return
	}

	_ = queue.Tell(JournalAnswer{Whisper: &said}, m.env, m.agent)
}

func (m merging) shallowly() []SinMark {
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

	rules = append(rules, custom.InForce(m.worktree).Enabled(project)...)

	return m.shallow(project.Judging(rules), rules)
}

func (m merging) deadline() time.Duration {
	if !m.limited {
		return noDeadline
	}

	return m.window - max(m.window/10, 200*time.Millisecond)
}

const noDeadline = time.Hour

// merging is a worker's merge as the event describes it: who it is, where its work stands, what its branch was cut from, the files it names, and how long the journal waits for this answer.
type merging struct {
	worker   string
	worktree string
	base     string
	files    []string
	window   time.Duration
	limited  bool
	role     string
	env      string
	agent    int
}

func mergeOf(line map[string]any) merging {
	fields := eventFields(line)
	window, limited := windowIn(fields)

	return merging{
		worker:   worker4Of(fields),
		worktree: asText(fields["worktree"]),
		base:     asText(fields["base"]),
		files:    namedFiles(fields["files"]),
		window:   window,
		limited:  limited,
		role:     roleIn(fields),
		env:      asText(firstOf(line["environment"], fields["environment"])),
		agent:    asAgent(firstOf(line["agent"], fields["agent"])),
	}
}

func (m merging) judgedByRole() bool {
	if m.role == "" {
		return true
	}

	return !workerRoles[m.role]
}

var workerRoles = map[string]bool{
	"worker":  true,
	"helper":  true,
	"medior":  true,
	"senior":  true,
	"builder": true,
}

func roleIn(fields map[string]any) string {
	return strings.ToLower(strings.TrimSpace(asText(firstOf(fields["role"], fields["agent_role"]))))
}

func windowIn(fields map[string]any) (time.Duration, bool) {
	named, given := fields["window_ms"]
	if !given {
		return DeclaredWindow, true
	}

	if named == nil {
		return 0, false
	}

	if window, read := milliseconds4Of(named); read {
		return window, true
	}

	return DeclaredWindow, true
}

func milliseconds4Of(named any) (time.Duration, bool) {
	switch window := named.(type) {
	case float64:
		return time.Duration(window) * time.Millisecond, true
	case int:
		return time.Duration(window) * time.Millisecond, true
	case json.Number:
		if read, err := window.Int64(); err == nil {
			return time.Duration(read) * time.Millisecond, true
		}
	case string:
		if read, err := strconv.ParseInt(strings.TrimSpace(window), 10, 64); err == nil {
			return time.Duration(read) * time.Millisecond, true
		}
	}

	return 0, false
}

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
	rules = append(rules, custom.InForce(m.worktree).Enabled(project)...)
	project = project.Judging(rules)

	switch {
	case !m.limited, m.window >= wholeTreeWindow:
		return m.deep(project, rules)
	case m.window >= changedFilesWindow:
		return m.shallow(project, rules)
	}

	return nil
}

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

func (m merging) roots(project config.Config) []string {
	if len(project.Paths) == 0 {
		return []string{m.worktree}
	}

	return config.Absolute(m.worktree, project.Paths)
}

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

func (m merging) cutFrom() string {
	if m.base != "" {
		return m.base
	}

	if branch := git.CurrentBranch(os.Getenv("JOURNAL_ROOT")); branch != "" {
		return branch
	}

	return scope.BaseOf(m.worktree)
}

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

			if mark := marking.Of(rule, match, lines[match.File()]); !mark.IsFiled() {
				marks = append(marks, mark)
			}
		}
	}

	return marks
}

func mergeReason(worker string, marks []SinMark, root string) string {
	return mergeTold(worker, "", root, marks)
}

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
