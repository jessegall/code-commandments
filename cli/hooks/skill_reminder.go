package hooks

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/skill"
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
	files := edited(event, project)

	single := slices.DeleteFunc(slices.Clone(enabled), detectors.ReadsBeyondOneFile)

	reported := ReportedIn(event.Workspace())
	marking := Marks(event.Root)
	var activity []SinMark
	var fresh []SinMark
	var unreadable []string
	var spentReading time.Duration

	judged := slices.DeleteFunc(slices.Clone(files), func(file string) bool {
		return !project.Writes(source.OfFile(file))
	})

	together, spent, whole := AgainstTheProgramTogether(marking, judged,
		func(file string) []detectors.Detector {
			return project.RulesFor(checkoutOf(event.Root, file), file, enabled)
		},
		func(file string) git.ChangedLines { return event.ChangedLines(file) }, event.Root)

	spentReading += spent

	if !whole {
		Warming(event.Root, project)
	}

	asked := Asked{}

	for _, file := range files {
		if !project.Writes(source.OfFile(file)) {
			continue
		}

		marks, against := together[file]
		if !against {
			asked[source.Relative(event.Root, file)] = sinsOf(project.RulesFor(checkoutOf(event.Root, file), file, single))
			var unread error
			var read time.Duration
			marks, read, unread = marksIn(marking, file,
				project.RulesFor(checkoutOf(event.Root, file), file, single), event.ChangedLines(file))
			spentReading += read

			if unread != nil {
				unreadable = append(unreadable, filepath.Base(file)+": "+unread.Error())
			}
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

	if len(unreadable) > 0 {
		said = append(said, "Code Commandments could not read what this edit changed, so nothing of it was judged, "+
			"which is not the same as finding no sins: "+strings.Join(unreadable, "; "))
	}

	response := Silent()
	if len(said) > 0 {
		response = Injecting(event.Name(), strings.Join(said, "\n\n"), false)
	}

	response.Activity = activity
	response.Judged = files
	response.Asked = asked
	response.Requires = owed(fresh)
	response.ReadMs = spentReading.Milliseconds()

	return response
}

func edited(event Event, project config.Config) []string {
	touched := TouchedIn(event.Workspace(), event.Root, project)
	var files []string
	shell, more := event.IsTool("Bash"), false

	switch {
	case shell:
		files, more = touched.Claim(touchedLimit)
	case len(batchedIn(event.payload)) > 0:
		for _, named := range batchedIn(event.payload) {
			if file := judgedFile(event.Root, named, project); file != "" {
				files = append(files, file)
			}
		}

		touched.MarkSeen()
	default:
		if file := judgedFile(event.Root, event.FilePath(), project); file != "" {
			files = []string{file}
		}

		touched.MarkSeen()
	}

	left := budget
	var affordable []string

	for _, file := range files {
		info, err := os.Stat(file)
		if err != nil {
			continue
		}
		if info.Size() > int64(budget) {
			continue
		}
		if info.Size() > int64(left) {
			more = true

			continue
		}

		left -= int(info.Size())
		affordable = append(affordable, file)
	}

	if shell && !more {
		touched.MarkSeen()
	}

	return affordable
}

func judgedFile(root, file string, project config.Config) string {
	if file == "" {
		return ""
	}

	absolute := file
	if !strings.HasPrefix(file, "/") {
		absolute = strings.TrimRight(root, "/") + "/" + strings.TrimLeft(file, "/")
	}

	if resolved, err := filepath.EvalSymlinks(absolute); err == nil {
		absolute = resolved
	}

	if !project.IsJudged(checkoutOf(root, absolute), absolute) {
		return ""
	}

	if info, err := os.Stat(absolute); err != nil || !info.Mode().IsRegular() {
		return ""
	}

	return absolute
}

func projectOf(folder string) string {
	if project := git.ProjectRoot(folder); project != "" {
		return project
	}

	return folder
}

func checkoutOf(root, file string) string {
	checkout := git.Root(file)
	if checkout == "" || checkout == strings.TrimRight(root, "/") {
		return root
	}

	if !git.BelongsTo(checkout, projectOf(root)) {
		return root
	}

	return checkout
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
		if !detectors.Judges(rule, language.Engine()) {
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
		if recover() != nil {
			matches = nil
		}
	}()

	return rule.Find(codebase)
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
