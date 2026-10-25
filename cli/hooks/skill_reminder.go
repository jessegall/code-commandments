package hooks

import (
	"github.com/jessegall/code-commandments/cli/binary"
	"os"
	"slices"
	"strconv"
	"strings"

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
	// touchedLimit is how many changed files one shell command is checked over.
	touchedLimit = 5
	// budget is how many bytes of source one check reads, so a burst of large files never stalls an edit.
	budget = 120_000
)

// SkillReminder checks what an edit changed against the rules that can judge one file, the project's own among them, and names the skill
// that teaches the fix of each finding it has not named before; a shell command's edits are the judged
// files changed since the last look.
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

	project, _ := config.Load(event.Root)
	files := edited(event, project)

	enabled, err := project.Enabled(config.InstalledIn(event.Root))
	if err != nil {
		return Silent()
	}

	enabled = append(enabled, custom.Load(event.Root).Enabled(project)...)

	single := slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile)

	reported := ReportedIn(event.Workspace())
	marking := Marks(event.Root)
	var activity []SinMark
	var fresh []SinMark

	for _, file := range files {
		if !project.Writes(source.OfFile(file)) {
			continue
		}

		marks := marksIn(marking, file, single, event.ChangedLines(file))
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

	response := Silent()
	if len(fresh) > 0 {
		response = Injecting(event.Name(), nudge(event, fresh), false)
	}

	response.Activity = activity
	response.Judged = files

	return response
}

// edited are the files the moment changed, within the budget: a writer's own file when it is judged, or the
// judged files a shell command changed since the last look.
func edited(event Event, project config.Config) []string {
	touched := TouchedIn(event.Workspace(), event.Root, project)
	var files []string
	shell, more := event.IsTool("Bash"), false

	if shell {
		files, more = touched.Claim(touchedLimit)
	} else {
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
		// A file no budget could ever afford is one the tool refuses to read whole; it is past this check for
		// good, and `judge` is what still finds it. One that merely does not fit what is left of this check's
		// budget waits for the next, so a burst of writes is judged over several calls instead of losing its tail.
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
	if file == "" || !project.IsJudged(root, file) {
		return ""
	}

	absolute := file
	if !strings.HasPrefix(file, "/") {
		absolute = strings.TrimRight(root, "/") + "/" + strings.TrimLeft(file, "/")
	}

	if info, err := os.Stat(absolute); err != nil || !info.Mode().IsRegular() {
		return ""
	}

	return absolute
}

// marksIn are every sin the rules find in the file, each marked touched when it stands on a line the working
// tree changed, and filed when this project has already reported it.
func marksIn(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) []SinMark {
	language := source.OfFile(file)

	codebase, err := scan.OneFile(file).Load()
	if err != nil {
		return nil
	}

	var marks []SinMark

	for _, rule := range rules {
		if engine, _ := detectors.EngineOf(rule); engine != language.Engine() {
			continue
		}

		for _, match := range safely(rule, codebase) {
			marks = append(marks, marking.Of(rule, match, changed))
		}
	}

	return marks
}

// bySkill are the touched marks as the check names them, by the skill that fixes them, and the skills in the
// order they were found.
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

// safely is what the rule finds, or nothing when it fails: one broken rule never silences the others.
func safely(rule detectors.Detector, codebase *engine.Codebase) (matches []engine.Match) {
	defer func() {
		if recover() != nil {
			matches = nil
		}
	}()

	return rule.Find(codebase)
}

// attribution names what was changed, as the nudge opens with it.
// nudge is what a check says the moment an edit commits a sin: each new sin, the file and line it is in, and the
// one command that judges that file for that sin. It names them rather than sending the agent to find out what it
// was just told, and it says nothing else — not the files it read, not how many it left out, not the skill, which
// the command itself prints.
func nudge(event Event, marks []SinMark) string {
	command := func(mark SinMark) string {
		return "`" + binary.Invocation(event.Root) + " judge " + mark.FileFrom(event.Root) +
			" --sin=" + mark.Rule.Sin().Definition().Name + "`"
	}
	// The skill is named with the sin, always: a mark that says only what is wrong leaves the reader to guess the
	// discipline that says how to fix it, and a guess is how a finding gets silenced instead of fixed.
	teaches := func(mark SinMark) string {
		return "load `" + skill.IDFor(mark.Rule.Sin().Definition().Slug()) + "`"
	}

	if len(marks) == 1 {
		return "Code Commandments — " + marks[0].ShownFrom(event.Root) + ": " + teaches(marks[0]) +
			", then fix it at its source. Run " + command(marks[0]) + "."
	}

	lines := []string{"Code Commandments — " + strconv.Itoa(len(marks)) + " new sins:"}
	for _, mark := range marks {
		lines = append(lines, "  • "+mark.ShownFrom(event.Root)+" — "+teaches(mark)+", run "+command(mark))
	}

	return strings.Join(lines, "\n")
}
