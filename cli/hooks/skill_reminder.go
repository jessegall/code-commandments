package hooks

import (
	"os"
	"time"
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

	// The configuration and the project's own rules come from the PROJECT, never from a worktree. A worker has
	// no copy of its own to keep in step, and a change in the plugin's configuration applies to every worker at
	// once. What a worktree answers for is the root a relative path is joined to, which is its own checkout.
	home := projectOf(event.Root)

	project, _ := config.Load(home)

	enabled, err := project.Enabled(config.InstalledIn(home))
	if err != nil {
		return Silent()
	}

	enabled = append(enabled, custom.InForce(home).Enabled(project)...)

	// A rule of the project's own may name the folders it judges in its own file, so the config is read with what
	// the rules declare before anything asks which files are looked at or which rules may mark one.
	project = project.Judging(enabled)
	files := edited(event, project)

	// The rules that answer from the file they report in, which is all a run with no base of the branch can
	// ask. The clone matters: DeleteFunc writes through the slice it is given, so narrowing in place would take
	// those rules out of the enabled set every other reader here uses.
	single := slices.DeleteFunc(slices.Clone(enabled), detectors.ReadsBeyondOneFile)

	reported := ReportedIn(event.Workspace())
	marking := Marks(event.Root)
	var activity []SinMark
	var fresh []SinMark
	var spentReading time.Duration

	for _, file := range files {
		if !project.Writes(source.OfFile(file)) {
			continue
		}

		// A file inside a folder the project excludes is marked only by the rules it let in there, where a file
		// of its declared paths is marked by them all.
		//
		// Against a view of the branch's base every rule can be asked, since the view IS the program; with no
		// base held the file is judged alone, which only the rules that answer from one file may be asked, and
		// a base is read in the background so the next edit is answered against the program.
		// The folders a rule names are joined to the checkout the FILE lies in, which for a worker is its own
		// worktree: the folders are the project's, the root is the file's.
		in := checkoutOf(event.Root, file)

		marks, read, whole := AgainstTheProgram(marking, file, project.RulesFor(in, file, enabled), event.ChangedLines(file), event.Root)
		if !whole {
			marks, read = marksIn(marking, file, project.RulesFor(in, file, single), event.ChangedLines(file))
			Warming(event.Root, project)
		}

		spentReading += read
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
	response.Requires = owed(fresh)
	response.ReadMs = spentReading.Milliseconds()

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
	if file == "" {
		return ""
	}

	// Absolute first, and only then which checkout it lies in: a file the event names is RELATIVE to the root,
	// and asking git about a relative path asks about this process's own folder, which is the plugin's. That is
	// what left a worker's edits unjudged even once the gate knew to look at the file's own checkout.
	absolute := file
	if !strings.HasPrefix(file, "/") {
		absolute = strings.TrimRight(root, "/") + "/" + strings.TrimLeft(file, "/")
	}

	if !project.IsJudged(checkoutOf(root, absolute), absolute) {
		return ""
	}

	if info, err := os.Stat(absolute); err != nil || !info.Mode().IsRegular() {
		return ""
	}

	return absolute
}

// projectOf is the project a run belongs to: the main checkout of the repository the folder lies in, so a
// worker in a worktree reads the project's configuration and the project's own rules rather than a copy of its
// own. The folder itself where it lies in no repository.
func projectOf(folder string) string {
	if project := git.ProjectRoot(folder); project != "" {
		return project
	}

	return folder
}

// checkoutOf is the root a path the project declares is joined to for this file: the checkout the file lies in,
// where that is a checkout of this project, and the given root otherwise.
//
// A worktree is a checkout of its own, cut inside the project's hidden folder, so a worker's file joined to the
// PROJECT's root reads as a path outside every declared folder: such a file was dropped before any rule ran, and
// the plugin answered a worker's events with nothing while a judging of that file found eight sins.
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

// marksIn are every sin the rules find in the file, each marked touched when it stands on a line the working
// tree changed, and filed when this project has already reported it.
func marksIn(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) ([]SinMark, time.Duration) {
	language := source.OfFile(file)

	reading := time.Now()
	codebase, err := scan.OneFile(file).Load()
	read := time.Since(reading)

	if err != nil {
		return nil, read
	}

	var marks []SinMark

	for _, rule := range rules {
		// Every engine the rule judges, not the first one its file happens to name: a rule naming go and
		// python was never asked about a python file here, which is a rule a project wrote switched off in
		// its live check while a judge still reported it.
		if !detectors.Judges(rule, language.Engine()) {
			continue
		}

		for _, match := range safely(rule, codebase) {
			// Only a finding IN this file is this file's mark. For most languages the scan loaded nothing
			// else, but Go's types come from a whole package, so a scan shown one Go file reads its siblings
			// too, and a sin one of them holds is the sibling's own business.
			if match.File() != file {
				continue
			}
			marks = append(marks, marking.Of(rule, match, changed))
		}
	}

	return marks, read
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
// owed are the ids of the skills the fresh findings teach, each once. A journal holds the agent to these, where
// the nudge alone only names them, and a sin fixed from memory of a skill that was never read is how a finding
// gets silenced rather than resolved. A project's own rule is named here like a shipped one, since its skill is
// published under the same prefix.
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

// nudge is what a check says the moment an edit commits a sin: each new sin, the file and lines it is in, the thing
// it is about where the finding names one, and the skill that teaches the fix. It names them rather than sending
// the agent to find out what it was just told.
//
// Every word of it is read by an agent on every edit, so it is said once. The findings of one sin in one file are
// one line, naming that file and that skill once however many there are, because three findings used to repeat the
// path, the skill and a whole judge command three times over. The command is gone with them: an agent that is told
// the file and the sin can judge it, and the line that said how was half of the line.
//
// The skill is named with every sin raised, always, and that is not a length to save. A mark that says only what is
// wrong leaves the reader to guess the discipline that says how to fix it, and a guess is how a finding gets
// silenced instead of fixed; a compaction drops a loaded skill without saying so, which is why the discipline
// itself tells an agent to assume it is NOT loaded. Naming it once a session would trade that for a few words.
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

// raised is every finding of one sin in one file, which is what the agent is told about as one thing: the file and
// the skill are the same for all of them, and only the line and the name they stand on differ.
type raised struct {
	file  string
	sin   string
	skill string
	lines []string
	named []string
}

// line is what the agent reads for them: the file once, the lines it holds them on, the sin, what it is about where
// the findings name something, and the skill that teaches the fix.
func (r raised) line(root string) string {
	said := r.file + ":" + strings.Join(r.lines, ",") + " " + r.sin

	if len(r.named) > 0 {
		said += " (" + strings.Join(r.named, ", ") + ")"
	}

	return said + " — load `" + r.skill + "`"
}

// byFileAndSin gathers the marks by the file and the sin they share, in the order they were found, so the lines
// read in the order the file does.
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

		// What the finding is about, where it is about a thing with a name: a variable, a method, a parameter. It
		// is the fix in one word for the rules about naming, and it saves the agent reading the file to find out
		// which of three names on the line was meant. A finding about a block names nothing, and says nothing.
		if named := mark.Match.Name(); named != "" && !slices.Contains(held.named, named) {
			held.named = append(held.named, named)
		}

		grouped[key] = held
	}

	return grouped, order
}
