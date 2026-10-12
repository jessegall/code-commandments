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
	sins := map[string][]string{}
	var skills []string
	var activity []SinMark

	for _, file := range files {
		if !project.Writes(source.OfFile(file)) {
			continue
		}

		marks := marksIn(file, single, git.ChangedLinesOf(event.Root, file))
		activity = append(activity, marks...)

		found, order := bySkill(marks)
		unseen := reported.Unseen(file, found, order)

		for _, slug := range order {
			if _, has := unseen[slug]; !has {
				continue
			}

			if !slices.Contains(skills, slug) {
				skills = append(skills, slug)
			}

			sins[slug] = append(sins[slug], unseen[slug]...)
		}
	}

	response := Silent()
	if len(sins) > 0 {
		response = Injecting(event.Name(), nudge(event, sins), false)
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

	if event.IsTool("Bash") {
		files = touched.Claim(touchedLimit)
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
		if err != nil || info.Size() > int64(left) {
			continue
		}

		left -= int(info.Size())
		affordable = append(affordable, file)
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
// tree changed.
func marksIn(file string, rules []detectors.Detector, changed git.ChangedLines) []SinMark {
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
			marks = append(marks, MarkOf(rule, match, changed))
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
// nudge is the one line a check says the moment an edit commits a sin: how many are new, and the command that
// shows them. It says it at once, which is the point of the check, but the detail — the files, each sin's path and
// line, the skill to load — costs the agent's context only when it asks, since an agent told there are three goes
// looking where one named inline is fixed alone and the rest never judged.
func nudge(event Event, sins map[string][]string) string {
	found := 0
	for _, named := range sins {
		found += len(slices.Compact(slices.Sorted(slices.Values(named))))
	}
	sin := "sins"
	if found == 1 {
		sin = "sin"
	}

	return "Code Commandments — " + strconv.Itoa(found) + " new " + sin + ". `" +
		binary.Invocation(event.Root) + " judge --changes` to see them."
}
