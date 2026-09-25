package hooks

import (
	"github.com/jessegall/code-commandments/cli/binary"
	"os"
	"path/filepath"
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
	// shownPerSkill is how many findings of one skill a nudge names before it counts the rest.
	shownPerSkill = 3
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

	single := slices.DeleteFunc(enabled, func(detector detectors.Detector) bool {
		_, wholeTree := detector.(detectors.WholeTree)

		return wholeTree
	})

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
		response = Injecting(event.Name(), nudge(event, files, sins, skills), false)
	}

	response.Activity = activity

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

	codebase, err := scan.Sources{language: {file}}.Load()
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
func attribution(event Event, files []string) string {
	var names []string
	for _, file := range files {
		names = append(names, filepath.Base(file))
	}

	named := strings.Join(names, "`, `")

	switch {
	case !event.IsTool("Bash"):
		return "the edit you just made to `" + named + "`"
	case len(files) == 1:
		return "`" + named + "`, changed since the last check,"
	default:
		return "the files changed since the last check (`" + named + "`)"
	}
}

func nudge(event Event, files []string, sins map[string][]string, skills []string) string {
	lines := []string{"Code Commandments — " + attribution(event, files) + " breaks a rule. " +
		"Fix it now, at its SOURCE, while the code is still in front of you:"}

	for _, slug := range skills {
		found := sins[slug]

		var unique []string
		for _, finding := range found {
			if !slices.Contains(unique, finding) {
				unique = append(unique, finding)
			}
		}

		shown := unique[:min(shownPerSkill, len(unique))]
		line := "  • " + strings.Join(shown, "; ")

		if rest := len(unique) - len(shown); rest > 0 {
			line += " (+" + strconv.Itoa(rest) + " more)"
		}

		lines = append(lines, line+"\n    LOAD the skill `"+skill.IDFor(slug)+"` before fixing — load it even if you believe you already have.")
	}

	lines = append(lines, "Run `"+binary.Invocation(event.Root)+" info <sin>` if a rule is not one you recognise. "+
		"This check reads a file at a time, so it is not the whole picture — `judge` still is.")

	return strings.Join(lines, "\n")
}
