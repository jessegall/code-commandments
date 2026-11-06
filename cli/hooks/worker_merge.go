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
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const (
	deliveringEvent = "worker_delivering"
	mergeEvent      = "worker_merging"
	mergedEvent     = "worker_merge_result"
)

const mergeSinsShown = 5

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

	marks := work.sinsWithin()
	if len(marks) == 0 {
		return ""
	}

	refusal := mergeReason(work.worker, marks)

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

func (m merging) sinsWithin() []SinMark {
	within := time.After(m.deadline())

	shallow := make(chan []SinMark, 1)
	go func() { shallow <- m.shallowly() }()

	var held []SinMark

	select {
	case marks := <-shallow:
		held = marks
	case <-within:
		return nil
	}

	answered := make(chan []SinMark, 1)
	go func() { answered <- m.sins() }()

	select {
	case marks := <-answered:
		return marks
	case <-within:
		return held
	}
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
