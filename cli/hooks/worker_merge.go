package hooks

import (
	"encoding/json"
	"maps"
	"os"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
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
	if handInsJudgedAfter(line) {
		judgedAfterTheHandIn(line)

		return ""
	}

	if !handInsJudged(line) {
		return ""
	}

	return mergeOf(line).answered()
}

func judgedAfterTheHandIn(line map[string]any) {
	if raw, err := json.Marshal(line); err == nil {
		judgeDetached(raw)
	}
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
}

func (work merging) records(why Narrowed, sins int, waited string) {
	project := projectOf(work.worktree)
	if project == "" || !recorded(project) {
		return
	}

	Record(bookkeepingIn(project), Judged{
		At: time.Now(), Event: deliveringEvent, Kind: "worktree", Checkout: work.worktree,
		Held: why == "", Why: why, Sins: sins, Waited: waited, Said: "the hand-in",
	})
}

func (work merging) waited(since time.Time) string {
	limit := "no limit"
	if !work.unlimited {
		limit = "a window of " + strconv.FormatInt(work.window.Milliseconds(), 10) + "ms"
	}

	return "waited " + strconv.FormatInt(time.Since(since).Milliseconds(), 10) + "ms of " + limit
}

func (work merging) answered() string {
	began := time.Now()
	found := work.sinsWithin()

	work.records(found.reason(), len(found.marks), work.waited(began))

	if found.got == gotWhole && len(found.marks) == 0 {
		return ""
	}

	if found.got == gotWhole {
		refusal := mergeReason(work.worker, found.marks, work.worktree)

		return (JournalAnswer{Refuse: &refusal}).Line4()
	}

	said := Unjudged(work.worker+"'s hand-in was not judged in full", string(found.got), "")
	work.keeps(said)

	if len(found.marks) == 0 {
		return (JournalAnswer{Whisper: &said}).Line4()
	}

	refusal := mergeReason(work.worker, found.marks, work.worktree)

	return (JournalAnswer{Refuse: &refusal, Whisper: &said}).Line4()
}

// handIn is what a hand-in's judging came to: the marks it found, and how far it got, which a slice of marks alone cannot say.
type handIn struct {
	marks []SinMark
	got   gotTo
	why   Narrowed
}

func (h handIn) reason() Narrowed {
	if h.why == "" {
		return Narrowed(h.got)
	}

	return Narrowed(string(h.got) + ": " + string(h.why))
}

type gotTo string

const (
	gotNowhere gotTo = "nothing of this hand-in was judged: the time the journal allows ran out before even the files it changed could be read"
	gotPartly  gotTo = "only the files this hand-in changed were judged, and the rules that read across files were not asked, because the time the journal allows ran out"
	gotWhole   gotTo = ""

	withoutTheTree gotTo = "only the rules that answer from one file were asked about the files this hand-in changed, because no tree of this project is held to answer the rest from, and the project is never read whole to make up for it"
)

const (
	judgedLate        Narrowed = "judged after the journal stopped waiting"
	judgedAfterHandIn Narrowed = "judged after the hand-in went through"
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
		go m.tellsLater(shallow)

		return handIn{got: gotNowhere}
	}

	answered := make(chan handIn, 1)
	go func() { answered <- m.sins() }()

	select {
	case found := <-answered:
		return found
	case <-within:
		later := make(chan []SinMark, 1)
		go func() { later <- (<-answered).marks }()
		go m.tellsLater(later)

		return handIn{marks: held, got: gotPartly}
	}
}

func (m merging) tellsLater(answered chan []SinMark) {
	marks, came := <-answered
	if !came {
		return
	}

	m.tells(marks, judgedLate, " (judged after the journal stopped waiting)")
}

func (m merging) judgesAfterwards() {
	m.unlimited = true

	m.tells(m.sins().marks, judgedAfterHandIn, " (judged after the hand-in went through)")
}

func (m merging) tells(marks []SinMark, why Narrowed, about string) {
	m.records(why, len(marks), "")

	if len(marks) == 0 {
		return
	}

	said := mergeTold(m.worker, about, m.worktree, marks)

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
	m.since = git.MergeBase(m.worktree, m.cutFrom())

	return m.shallow(project.Judging(rules), rules)
}

func (m merging) deadline() time.Duration {
	if m.unlimited {
		return noDeadline
	}

	return m.window - max(m.window/10, 200*time.Millisecond)
}

const noDeadline = time.Hour

// merging is a worker's merge as the event describes it: who it is, where its work stands, what its branch was cut from, the files it names, and how long the journal waits for this answer.
type merging struct {
	worker    string
	worktree  string
	base      string
	files     []string
	window    time.Duration
	unlimited bool
	role      string
	env       string
	agent     int
	editor    int
	since     string
}

func mergeOf(line map[string]any) merging {
	fields := eventFields(line)
	window, limited := windowIn(fields)

	return merging{
		worker:    worker4Of(fields),
		worktree:  asText(fields["worktree"]),
		base:      asText(fields["base"]),
		files:     namedFiles(fields["files"]),
		window:    window,
		unlimited: !limited,
		role:      roleIn(fields),
		env:       asText(firstOf(line["environment"], fields["environment"])),
		agent:     asAgent(firstOf(line["agent"], fields["agent"])),
		editor:    asAgent(fields["worker"]),
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

func (m merging) sins() handIn {
	if m.worktree == "" {
		return handIn{got: gotWhole}
	}

	project, err := config.Load(m.worktree)
	if err != nil {
		return handIn{got: gotWhole}
	}

	rules, err := project.Enabled(config.InstalledIn(m.worktree))
	if err != nil {
		return handIn{got: gotWhole}
	}
	rules = append(rules, custom.InForce(m.worktree).Enabled(project)...)
	project = project.Judging(rules)

	if !m.unlimited && m.window < changedFilesWindow {
		return handIn{got: gotWhole}
	}

	m.since = git.MergeBase(m.worktree, m.cutFrom())
	judged := m.deep(project, rules)

	if since := git.MergeBase(m.worktree, m.cutFrom()); since != m.since {
		m.since = since

		return m.deep(project, rules)
	}

	return judged
}

func (m merging) deep(project config.Config, rules []detectors.Detector) handIn {
	changed := m.changed(project)
	if len(changed) == 0 {
		return handIn{got: gotWhole}
	}

	root := projectOf(m.worktree)

	found, _, why := AgainstTheProgramOnceRead(Marks(root).In(m.worktree).CutFrom(m.since), slices.Sorted(maps.Keys(changed)),
		func(string) []detectors.Detector { return rules },
		func(file string) git.ChangedLines { return git.ChangedLinesSince(m.worktree, m.since, file) }, root)
	if why != "" {
		return handIn{marks: m.shallow(project, rules), got: withoutTheTree, why: why}
	}

	var marks []SinMark
	for _, file := range slices.Sorted(maps.Keys(found)) {
		marks = append(marks, touchedAndUnfiled(found[file])...)
	}

	if len(marks) == 0 && m.tracksEdits() {
		WorkerEditsIn(os.Getenv(pluginData)).Clear(m.editor)
	}

	return handIn{marks: marks, got: gotWhole}
}

func (m merging) shallow(project config.Config, rules []detectors.Detector) []SinMark {
	changed := m.changed(project)
	if len(changed) == 0 {
		return nil
	}

	files := slices.Sorted(maps.Keys(changed))

	marks := map[string]string{}
	for _, file := range files {
		marks[file] = warm.Mark(file)
	}

	codebase, err := scan.Walk(files, source.Excluded{}).Load()
	if err != nil {
		return nil
	}

	parsed.keeps(codebase.Streams(), marks)

	return m.marks(codebase, slices.DeleteFunc(slices.Clone(rules), detectors.ReadsBeyondOneFile), changed)
}

func (m merging) tracksEdits() bool {
	return m.editor != 0 && os.Getenv(pluginData) != ""
}

func (m merging) named() map[string]bool {
	if len(m.files) > 0 {
		return git.Listed(m.worktree, m.files)
	}

	if m.tracksEdits() {
		return git.Listed(m.worktree, WorkerEditsIn(os.Getenv(pluginData)).Of(m.editor))
	}

	listed, _ := git.ChangedVsBranch(m.worktree, m.cutFrom())

	return listed
}

func (m merging) changed(project config.Config) map[string]bool {
	listed := m.named()

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
	marking := Marks(projectOf(m.worktree)).In(m.worktree)
	lines := map[string]git.ChangedLines{}
	var marks []SinMark

	for _, rule := range rules {
		for _, match := range safely(rule, codebase) {
			if !changed[match.File()] {
				continue
			}
			if _, read := lines[match.File()]; !read {
				lines[match.File()] = git.ChangedLinesSince(m.worktree, m.since, match.File())
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
