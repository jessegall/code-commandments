package hooks

import (
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
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const (
	deliveringEvent = "worker_delivering"
)

// DeliveryHeld judges a hand-in before it goes through, when that is chosen. A hand-in judged after it is answered
// and queued by the plugin's script, and reaches this binary only as the kept event of that run.
func DeliveryHeld(line map[string]any) string {
	return mergeOf(line).answered()
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

func (work merging) records(why Narrowed, sins int, waited string, times judgedTimes) {
	project := projectOf(work.worktree)
	if project == "" || !recorded(project) {
		return
	}

	Record(bookkeepingIn(project), Judged{
		At: time.Now(), Event: deliveringEvent, Kind: "worktree", Checkout: work.worktree,
		Held: why == "", Why: why, Sins: sins, Waited: waited, Said: "the hand-in",
		Parsed: times.files, Kept: times.kept, Missed: times.missed, ReadMs: times.read.Milliseconds(), ReadWaitMs: times.waited.Milliseconds(), RuleMs: times.rules.Milliseconds(), Asked: times.asked,
	})
}

func (work merging) waited(since time.Time) string {
	return "waited " + strconv.FormatInt(time.Since(since).Milliseconds(), 10) + "ms"
}

func (work merging) answered() string {
	began := time.Now()
	found := work.sins()

	work.records(found.reason(), len(found.marks), work.waited(began), found.times)

	if found.got == gotWhole && len(found.marks) == 0 {
		return ""
	}

	if found.got == gotWhole {
		refusal := mergeReason(work.worker, found.marks, work.worktree)
		work.keeps(refusal)

		return (JournalAnswer{Refuse: &refusal}).Line()
	}

	said := Unjudged(work.worker+"'s hand-in was not judged in full", string(found.got), "")
	work.keeps(said)

	if len(found.marks) == 0 {
		return (JournalAnswer{Whisper: &said}).Line()
	}

	refusal := mergeReason(work.worker, found.marks, work.worktree)

	return (JournalAnswer{Refuse: &refusal, Whisper: &said}).Line()
}

// handIn is what a hand-in's judging came to: the marks it found, and how far it got, which a slice of marks alone cannot say.
type handIn struct {
	marks []SinMark
	got   gotTo
	why   Narrowed
	times judgedTimes
}

func (h handIn) reason() Narrowed {
	if h.why == "" {
		return Narrowed(h.got)
	}

	return Narrowed(string(h.got) + ": " + string(h.why))
}

type gotTo string

const (
	gotWhole gotTo = ""

	checkoutNotRead gotTo = "the worktree could not be read, so no rule was asked about the files this hand-in changed"
)

const judgedAfterHandIn Narrowed = "judged after the hand-in went through"

func (m merging) judgesAfterwards() {
	found := m.sins()
	m.tells(found.marks, judgedAfterHandIn, " (judged after the hand-in went through)", found.times)
}

func (m merging) tells(marks []SinMark, why Narrowed, about string, times judgedTimes) {
	m.records(why, len(marks), "", times)

	if len(marks) == 0 {
		return
	}

	said := mergeTold(m.worker, about, m.worktree, marks)

	m.keeps(said)
	toldLater(JournalAnswer{Whisper: &said}, m.env, m.agent)
}

// merging is a worker's merge as the event describes it: who it is, where its work stands, what its branch was cut from, and the files it names.
type merging struct {
	worker   string
	worktree string
	base     string
	files    []string
	env      string
	agent    int
	editor   int
	since    string
}

func mergeOf(line map[string]any) merging {
	fields := eventFields(line)
	return merging{
		worker:   worker4Of(fields),
		worktree: asText(fields["worktree"]),
		base:     asText(fields["base"]),
		files:    namedFiles(fields["files"]),
		env:      asText(firstOf(line["environment"], fields["environment"])),
		agent:    asAgent(firstOf(line["agent"], fields["agent"])),
		editor:   asAgent(fields["worker"]),
	}
}

var workerRoles = func() map[string]bool {
	roles := map[string]bool{}
	for _, role := range scriptFacts.WorkerRoles {
		roles[role] = true
	}

	return roles
}()

func roleIn(fields map[string]any) string {
	return strings.ToLower(strings.TrimSpace(asText(firstOf(fields["role"], fields["agent_role"]))))
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

	files := slices.Sorted(maps.Keys(changed))
	marks, times, read := judgedWhole(m.worktree, project, rules, func(string) []detectors.Detector { return rules }, files,
		git.ChangedLinesSinceEach(m.worktree, m.since, files),
		Marks(projectOf(m.worktree)).In(m.worktree).CutFrom(m.since))
	if !read {
		return handIn{got: checkoutNotRead, times: times}
	}

	if len(marks) == 0 && m.tracksEdits() {
		WorkerEditsIn(os.Getenv(pluginData)).Clear(m.editor)
	}

	return handIn{marks: marks, got: gotWhole, times: times}
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

	if branch := git.CurrentBranch(os.Getenv(journalRoot)); branch != "" {
		return branch
	}

	return scope.BaseOf(m.worktree)
}

func (m merging) marks(codebase *engine.Codebase, rules []detectors.Detector, changed map[string]bool) []SinMark {
	marking := Marks(projectOf(m.worktree)).In(m.worktree)
	lines := map[string]git.ChangedLines{}
	changedLines := git.ChangedLinesSinceEach(m.worktree, m.since, slices.Collect(maps.Keys(changed)))
	var marks []SinMark

	for _, rule := range rules {
		for _, match := range safely(rule, codebase) {
			if !changed[match.File()] {
				continue
			}
			if _, read := lines[match.File()]; !read {
				lines[match.File()] = changedLines(match.File())
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
