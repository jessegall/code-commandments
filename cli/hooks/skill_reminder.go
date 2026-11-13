package hooks

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/walk"
)

const (
	touchedLimit = 5
	budget       = 120_000
)

// SkillReminder checks what an edit changed against the rules that can judge one file, the project's own among them, and names the skill that teaches the fix of each finding it has not named before; a shell command's edits are the judged files changed since the last look.
type SkillReminder struct{}

func (SkillReminder) Class() string { return "SkillReminder" }
func (SkillReminder) Summary() string {
	return "After an edit — including one made with the shell — checks the files against the rules that can judge one file and names the skill that teaches the fix."
}
func (SkillReminder) Bindings() []Binding {
	return bound("PostToolUse", append(append([]string(nil), writers...), "Bash"))
}
func (SkillReminder) SpeaksToSubagents() {}

func (SkillReminder) Handle(event Event) Response {
	if event.Name() != "PostToolUse" || !slices.Contains(append(append([]string(nil), writers...), "Bash"), event.Tool()) {
		return Silent()
	}

	home := projectOf(event.Root)

	project, _ := config.Load(home)

	enabled, err := project.Enabled(config.InstalledIn(home))
	if err != nil {
		return Silent()
	}

	enabled = append(enabled, custom.InForce(home).Enabled(project)...)

	project = project.Judging(enabled)
	files, lost := edited(event, project)
	lost = slices.DeleteFunc(lost, event.OnlyRemovedLinesOf)

	single := slices.DeleteFunc(slices.Clone(enabled), detectors.ReadsBeyondOneFile)

	reported := ReportedIn(event.Workspace())
	marking := Marks(projectOf(event.Root)).In(event.Root).CutFrom(cutOf(event))
	var activity []SinMark
	var fresh []SinMark
	var unreadable []string
	var spentReading time.Duration

	judged := slices.DeleteFunc(slices.Clone(files), func(file string) bool {
		return !project.Writes(source.OfFile(file))
	})

	asked := Asked{}
	narrowed := map[string]Narrowed{}
	askedCount := map[string]int{}
	notAsked := map[string]int{}

	for _, file := range judged {
		narrow := project.RulesFor(checkoutOf(event.Root, file), file, single)
		whole := project.RulesFor(checkoutOf(event.Root, file), file, enabled)
		relative := source.Relative(event.Root, file)
		asked[relative] = sinsOf(narrow)
		narrowed[relative] = judgedAlone
		askedCount[relative] = len(narrow)
		notAsked[relative] = len(whole) - len(narrow)

		marks, read, unread := marksIn(marking, file, narrow, event.ChangedLines(file))
		spentReading += read

		if unread != nil {
			unreadable = append(unreadable, filepath.Base(file)+": "+unread.Error())
		}

		activity = append(activity, marks...)

		found, order := bySkill(marks)
		unseen := reported.Unseen(file, found, order)

		named := map[string]bool{}
		for _, namings := range unseen {
			for _, finding := range namings {
				named[finding] = true
			}
		}

		for _, mark := range marks {
			if mark.Touched && named[mark.Found()] && !mark.IsFiled() {
				fresh = append(fresh, mark)
			}
		}
	}

	var said []string
	if len(fresh) > 0 {
		said = append(said, nudge(event, fresh))
	}

	if short := Shortfalls(SaidIn(event.Workspace()).Fresh(narrowed), askedCount, notAsked, event.wholeAnswerBy(binary.Judging)); short != "" {
		said = append(said, short)
	}

	if len(unreadable) > 0 {
		said = append(said, Unjudged("what this edit changed could not be read, so nothing of it was judged",
			strings.Join(unreadable, "; "), ""))
	}

	if len(files) == 0 && len(lost) > 0 {
		said = append(said, Unjudged("nothing of this edit was judged: "+strings.Join(lost, ", "),
			string(filesNotOnDisk)+", which is what a checkout cut again or removed looks like",
			event.wholeAnswerBy(binary.JudgingChanges)))
	}

	response := Silent()
	if len(said) > 0 {
		response = Injecting(event.Name(), strings.Join(said, "\n\n"), false)
	}

	response.Activity = activity
	response.Judged = files
	response.Asked = asked
	response.NotAsked = notAsked
	response.Narrowed = narrowed
	response.Requires = owed(fresh)
	response.ReadMs = spentReading.Milliseconds()

	return response
}

func edited(event Event, project config.Config) ([]string, []string) {
	touched := TouchedIn(event.Workspace(), event.Root, project)
	var files, lost []string
	shell, more := event.IsTool("Bash"), false

	switch {
	case shell:
		files, more = touched.Claim(touchedLimit)
	case len(batchedIn(event.payload)) > 0:
		for _, named := range batchedIn(event.payload) {
			file := judgedFile(event.Root, named, project)
			if file != "" {
				files = append(files, file)

				continue
			}

			if isSource(named) && inTheJudgedFolders(event.Root, named, project) {
				lost = append(lost, named)
			}
		}

		touched.MarkSeen()
	default:
		if file := judgedFile(event.Root, event.FilePath(), project); file != "" {
			files = []string{file}
		} else if event.FilePath() != "" && isSource(event.FilePath()) && inTheJudgedFolders(event.Root, event.FilePath(), project) {
			lost = append(lost, event.FilePath())
		}

		touched.MarkSeen()
	}

	left := budget
	var affordable []string

	for _, file := range files {
		info, err := os.Stat(file)
		if err != nil || scan.IsFrozen(file) {
			continue
		}
		if info.Size() > int64(budget) {
			dropped.TooLarge++

			continue
		}
		if info.Size() > int64(left) {
			dropped.PastBudget++
			more = true

			continue
		}

		left -= int(info.Size())
		affordable = append(affordable, file)
	}

	if shell && !more {
		touched.MarkSeen()
	}

	return affordable, lost
}

func isSource(file string) bool {
	_, named := contract.LanguageOfPath(file)

	return named
}

// Dropped are the files an edit named and the judging never looked at, by the reason it did not: the counters a
// session's record keeps, so the honesty of a run can be read as numbers rather than guessed at.
type Dropped struct {
	NotJudged  int
	NotAFile   int
	TooLarge   int
	PastBudget int
}

var dropped Dropped

func DroppedSoFar() Dropped {
	return dropped
}

func judgedFile(root, file string, project config.Config) string {
	if file == "" {
		return ""
	}

	absolute := absoluteOf(root, file)

	if checkout := checkoutOf(root, absolute); !project.IsJudged(checkout, absolute) {
		dropped.NotJudged++
		refused(file, judgedFolders(project, checkout))

		return ""
	}

	if info, err := os.Stat(absolute); err != nil || !info.Mode().IsRegular() {
		dropped.NotAFile++

		return ""
	}

	return absolute
}

func absoluteOf(root, file string) string {
	absolute := file
	if !strings.HasPrefix(file, "/") {
		absolute = strings.TrimRight(root, "/") + "/" + strings.TrimLeft(file, "/")
	}

	if resolved, err := walk.Resolved(absolute); err == nil {
		absolute = resolved
	}

	return absolute
}

func inTheJudgedFolders(root, file string, project config.Config) bool {
	absolute := absoluteOf(root, file)

	return project.IsJudged(checkoutOf(root, absolute), absolute)
}

func judgedFolders(project config.Config, checkout string) string {
	if len(project.Paths) == 0 {
		return "this project declares no folder to judge, so nothing under " + checkout + " is judged at all"
	}

	return "it lies outside the folders this project judges, which are " + strings.Join(project.Paths, ", ") +
		" under " + checkout
}

var refusals sync.Map

func refused(file string, why string) { refusals.Store(file, why) }

func RefusalOf(file string) string {
	why, found := refusals.Load(file)
	if !found {
		return ""
	}

	return why.(string)
}

func projectOf(folder string) string {
	if project := git.ProjectRoot(folder); project != "" {
		return project
	}

	return folder
}

func checkoutOf(root, file string) string {
	checkout := git.Root(absolutely(root, file))
	if checkout == "" || checkout == strings.TrimRight(root, "/") {
		return root
	}

	if !git.BelongsTo(checkout, projectOf(root)) {
		return root
	}

	return checkout
}

func absolutely(root, file string) string {
	if file == "" {
		return file
	}

	absolute := file
	if !filepath.IsAbs(file) {
		absolute = filepath.Join(strings.TrimRight(root, "/"), file)
	}

	if real, err := walk.Resolved(absolute); err == nil {
		return real
	}

	return absolute
}

func sinsOf(rules []detectors.Detector) []string {
	named := make([]string, 0, len(rules))
	for _, rule := range rules {
		named = append(named, rule.Sin().Definition().Name)
	}

	return named
}

func marksIn(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) ([]SinMark, time.Duration, error) {
	language := source.OfFile(file)

	reading := time.Now()
	codebase, err := scan.OneFile(file).Load()
	read := time.Since(reading)

	var incomplete scan.Incomplete
	if errors.As(err, &incomplete) {
		err = nil
	}

	if err != nil || codebase == nil {
		return nil, read, err
	}

	var marks []SinMark

	for _, rule := range rules {
		if !detectors.JudgesAny(rule, language.Engines()) {
			continue
		}

		for _, match := range safely(rule, codebase) {
			if match.File() != file {
				continue
			}
			marks = append(marks, marking.Of(rule, match, changed))
		}
	}

	return marks, read, nil
}

func bySkill(marks []SinMark) (map[string][]string, []string) {
	found := map[string][]string{}
	var order []string

	for _, mark := range marks {
		if !mark.Touched {
			continue
		}

		slug := mark.Rule.Sin().Definition().Slug()
		if _, seen := found[slug]; !seen {
			order = append(order, slug)
		}

		found[slug] = append(found[slug], mark.Found())
	}

	return found, order
}

func safely(rule detectors.Detector, codebase *engine.Codebase) (matches []engine.Match) {
	defer func() {
		if failure := recover(); failure != nil {
			matches = nil
			logRuleFailure(detectors.IdentityOf(rule), failure)
		}
	}()

	return detectors.FindRemembering(rule, codebase)
}

func owed(marks []SinMark) []string {
	var ids []string
	for _, mark := range marks {
		id := skill.IDFor(mark.Rule.Sin().Definition().Slug())
		if id == "" || slices.Contains(ids, id) {
			continue
		}
		ids = append(ids, id)
	}

	return ids
}

func nudge(event Event, marks []SinMark) string {
	grouped, order := byFileAndSin(event.Root, marks)

	if len(marks) == 1 {
		return "Code Commandments — " + grouped[order[0]].line(event.Root) + ", then fix it at its source."
	}

	lines := []string{"Code Commandments — " + strconv.Itoa(len(marks)) + " new sins, each to fix at its source:"}
	for _, key := range order {
		lines = append(lines, "  "+grouped[key].line(event.Root))
	}

	return strings.Join(lines, "\n")
}

// raised is every finding of one sin in one file, which is what the agent is told about as one thing: the file and the skill are the same for all of them, and only the line and the name they stand on differ.
type raised struct {
	file  string
	sin   string
	skill string
	lines []string
	named []string
}

func (r raised) line(root string) string {
	said := r.file + ":" + strings.Join(r.lines, ",") + " " + r.sin

	if len(r.named) > 0 {
		said += " (" + strings.Join(r.named, ", ") + ")"
	}

	return said + " — load `" + r.skill + "`"
}

func byFileAndSin(root string, marks []SinMark) (map[string]raised, []string) {
	grouped := map[string]raised{}
	var order []string

	for _, mark := range marks {
		file := mark.FileFrom(root)
		sin := mark.Rule.Sin().Definition().Name
		key := file + "\x00" + sin

		held, gathered := grouped[key]
		if !gathered {
			held = raised{file: file, sin: sin, skill: skill.IDFor(mark.Rule.Sin().Definition().Slug())}
			order = append(order, key)
		}

		held.lines = append(held.lines, strconv.Itoa(mark.Match.Line()))

		if named := mark.Match.Name(); named != "" && !slices.Contains(held.named, named) {
			held.named = append(held.named, named)
		}

		grouped[key] = held
	}

	return grouped, order
}
