package judge

import (
	"path/filepath"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/checklist"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/skill"
)

// twinsShown is how many twins a finding names before it counts the rest.
const twinsShown = 3

// customNote tells a reader a finding came from the project's own rule.
const customNote = "Rules marked `(custom)` are THIS project's own, from " +
	".commandments/custom/ — a wrong one is fixed there, never reported upstream."

// Report is a run's findings grouped under the skill that fixes each, for the console and the checklist.
type Report struct {
	path         string
	skills       []string
	bySkill      map[string][]engine.Finding
	total        int
	fixable      map[string]string
	scaffoldable map[string]string
	skipped      Skipped
	says         map[string]string
	filed        []Reported
}

// Saying gives the report the words each sin is described by, so a card can say what is wrong rather than name
// the rule that found it.
func (r Report) Saying(says map[string]string) Report {
	r.says = says

	return r
}

// Reporting gives the report the findings this project has already reported, which it shows and writes down but
// never counts: they are filed, and the run is clean of them.
func (r Report) Reporting(filed []Reported) Report {
	r.filed = filed

	return r
}

// NewReport groups the findings under their skills, each group in one total order (location, detector,
// scope) so the report reads the same whatever order the workers finished in. fixable and scaffoldable map
// a sin to the command that fixes it or scaffolds its helper.
func NewReport(path string, findings []engine.Finding, fixable, scaffoldable map[string]string, skipped Skipped) Report {
	report := Report{path: path, bySkill: map[string][]engine.Finding{}, total: len(findings), fixable: fixable, scaffoldable: scaffoldable, skipped: skipped}

	for _, finding := range findings {
		if _, seen := report.bySkill[finding.Skill]; !seen {
			report.skills = append(report.skills, finding.Skill)
		}

		report.bySkill[finding.Skill] = append(report.bySkill[finding.Skill], finding)
	}

	sort.Strings(report.skills)

	for _, group := range report.bySkill {
		sort.SliceStable(group, func(i, j int) bool {
			return compare(group[i], group[j]) < 0
		})
	}

	return report
}

func compare(a, b engine.Finding) int {
	if order := natural.Compare(a.Location, b.Location); order != 0 {
		return order
	}

	if order := strings.Compare(a.Detector, b.Detector); order != 0 {
		return order
	}

	return strings.Compare(a.Scope, b.Scope)
}

// Console is the report as the terminal shows it.
func (r Report) Console() string {
	var lines []string

	for _, slug := range r.skills {
		findings := r.bySkill[slug]
		lines = append(lines,
			"\n\033[1;33m"+slug+"\033[0m  ("+strconv.Itoa(len(findings))+")",
			"  \033[1;36m↳ "+loadInstruction(slug)+"\033[0m",
			"    \033[2mDon't fix from memory. If you think it's already loaded, assume it is NOT — a compaction may have dropped it — and load it again to be sure.\033[0m",
		)

		if hasCustom(findings) {
			lines = append(lines, "  \033[2m↳ "+customNote+"\033[0m")
		}

		for _, finding := range findings {
			lines = append(lines, "  \033[36m"+r.relative(finding.Location)+"\033[0m  "+finding.Scope+"  \033[2m["+finding.Rule()+"]\033[0m")

			if len(finding.Twins) > 0 {
				lines = append(lines, "    \033[2m↳ same shape as: "+r.twins(finding)+"\033[0m")
			}
		}

		for _, command := range commandsFor(findings, r.fixable) {
			lines = append(lines, "  \033[32m↳ auto-fixable: "+command+"\033[0m")
		}

		for _, command := range commandsFor(findings, r.scaffoldable) {
			lines = append(lines, "  \033[32m↳ scaffold the helper: "+command+"\033[0m")
		}
	}

	// The advice is about the findings above it, and the caveats about the run, so both stand before the count.
	// The count is LAST because it is the verdict: a reader who takes in one line of a report takes in that one,
	// and a card or a log tail that shows the final line shows the number rather than a note about reading a
	// skill.
	lines = append(lines,
		"\n\033[2m↳ the rule above all: trace each sin to where the value is BORN and fix it THERE — read fix-at-the-source.\033[0m",
		"\033[2m↳ don't recognise a rule above? `"+binary.Here()+" info <sin>` — what it flags, why it is a sin, the fix, an example.\033[0m",
	)

	if len(r.filed) > 0 {
		lines = append(lines, r.reportedConsole())
	}

	if !r.skipped.IsEmpty() {
		lines = append(lines, r.skipped.Console())
	}

	lines = append(lines, "\n\033[1m"+natural.Counted(r.total, "sin")+"\033[0m across "+natural.Counted(len(r.skills), "skill")+".")

	return strings.Join(lines, "\n")
}

// Checklist is the report as the worklist an agent works down, deleting each line as it fixes the sin.
func (r Report) Checklist() string {
	var out strings.Builder

	out.WriteString("# Code Commandments — " + strconv.Itoa(r.total) + " sins to fix\n\n" + r.skipped.Markdown() + checklistPreamble())

	for _, slug := range r.skills {
		findings := r.bySkill[slug]
		out.WriteString("\n## " + slug + "\n\n> ▶ **" + loadInstruction(slug) + ".** " +
			"It teaches every fix below. Don't work from memory, and don't assume it's still loaded " +
			"from earlier — a compaction can drop it silently — load it again if in any doubt.\n\n")

		for _, command := range commandsFor(findings, r.fixable) {
			out.WriteString("> ✎ Auto-fixable — run `" + command + "` to repent these for you.\n\n")
		}

		for _, command := range commandsFor(findings, r.scaffoldable) {
			out.WriteString("> 🛠 Scaffold the helper its fix uses — run `" + command + "`.\n\n")
		}

		if hasCustom(findings) {
			out.WriteString("> 🧾 " + customNote + "\n\n")
		}

		for _, finding := range findings {
			out.WriteString("- `" + finding.Location + "`  " + finding.Scope + "  [" + finding.Rule() + "]")

			if len(finding.Twins) > 0 {
				out.WriteString(" — same shape as " + r.twins(finding))
			}

			out.WriteString("\n")
		}
	}

	if len(r.filed) > 0 {
		out.WriteString(r.reportedChecklist())
	}

	return out.String()
}

// reportedConsole names the findings the project has reported, with the issue that holds each: a reader who did
// not file them learns where the argument is being had, and that nothing here is asking them to fix it.
func (r Report) reportedConsole() string {
	lines := []string{"\n\033[2m" + natural.Counted(len(r.filed), "finding") + " reported and left out of the count:\033[0m"}

	for _, held := range r.filed {
		lines = append(lines, "\033[2m  "+held.Finding.Location+"  ["+held.Finding.Rule()+"] — "+held.Row.Where()+"\033[0m")
	}

	return strings.Join(lines, "\n")
}

// reportedChecklist is the reported findings as the worklist shows them: a section of their own, at the end, with
// no skill to load and nothing to do. A reader works the sections above it; this one is there so a line nobody
// must fix is still a line nobody has forgotten.
func (r Report) reportedChecklist() string {
	var out strings.Builder

	out.WriteString("\n" + checklist.ReportedHeading + "\n\n> 🧾 " + natural.Counted(len(r.filed), "finding") +
		" below " + was(len(r.filed)) + " reported to code-commandments as wrong, and a maintainer is answering. " +
		"Do NOT fix these and do NOT delete these lines: the report stands until `" + binary.Here() +
		" report follow` says it has been answered. If you believe one of them IS a real sin after all, " +
		"`" + binary.Here() + " report forget <issue>` drops the report and the sin is yours again.\n\n")

	for _, held := range r.filed {
		out.WriteString("- `" + held.Finding.Location + "`  " + held.Finding.Scope + "  [" + held.Finding.Rule() + "] — " + held.Row.Where() + "\n")
	}

	return out.String()
}

// was is the verb for a count of findings, so a line reads as English either way.
func was(count int) string {
	if count == 1 {
		return "was"
	}

	return "were"
}

// checklistPreamble is how the checklist tells its reader to work it.
func checklistPreamble() string {
	return "> 🔱 **The rule above all — `fix-at-the-source`.** Every sin below is a SYMPTOM. " +
		"Before you change a line, trace the value to where it is BORN and fix it there; " +
		"the symptom (and often others) then disappears on its own. Never silence it with a " +
		"`?? default`, a cast, or a null-check.\n\n" +
		"**This file is your worklist. Work it straight down, deleting as you go — do NOT " +
		"stop to re-check.** For each line, top to bottom, do exactly this:\n\n" +
		"1. **LOAD the skill named in the section header.** It " +
		"teaches the fix; do NOT fix from memory. Even if you believe you already loaded it, treat " +
		"it as NOT loaded (a context compaction may have silently dropped its instructions while " +
		"leaving you the impression they're still there) and load it again before touching the " +
		"section. Once per section is enough.\n" +
		"   _Don't recognise the rule, or about to argue with it? Run " +
		"`" + binary.Here() + " info <sin>` first — it prints what the rule flags, WHY it is a " +
		"sin, how it is fixed, and a worked example. The detector name from the line works as the " +
		"argument. Guessing at a rule you have not read is how a finding gets silenced instead of " +
		"fixed._\n" +
		"2. Open the `file:line` and fix the sin at its source.\n" +
		"3. **Delete that line from this file.** Nothing else — no tick, no mark, no " +
		"strike-through. The deleted line IS the record that it's fixed.\n\n" +
		"**Do NOT re-run `judge`, re-scan, or re-verify between fixes.** That is slow and " +
		"pointless: the shrinking file is your only source of truth, and each deleted line " +
		"is its own confirmation. Do not pause to check your work — just fix, delete, and " +
		"move to the next line until none remain.\n\n" +
		"Work **wave by wave.** ONLY when this list is EMPTY, run `commandments judge` again. " +
		"If your fixes rippled into other files, it writes a fresh worklist — a new wave; work " +
		"it exactly the same way (fix, delete, no re-checks between). Repeat, judging only " +
		"between waves, until a run is clean and deletes this file.\n"
}

// loadInstruction tells the reader to load the skill that teaches a fix.
func loadInstruction(slug string) string {
	return "LOAD the skill `" + skill.IDFor(slug) + "` before fixing"
}

func (r Report) twins(finding engine.Finding) string {
	shown := finding.Twins[:min(twinsShown, len(finding.Twins))]
	relative := make([]string, len(shown))

	for i, twin := range shown {
		relative[i] = r.relative(twin)
	}

	listed := strings.Join(relative, ", ")

	if rest := len(finding.Twins) - len(shown); rest > 0 {
		listed += " (+" + strconv.Itoa(rest) + " more)"
	}

	return listed
}

// relative is a location as the console shows it: under the judged path, without it.
// Said is the report as a journal's card shows it: a line per finding, in the words a person would use, and a
// closing count of the sins and the files holding them. A card is read by whoever is working, not by a terminal,
// so it carries no colour, no node kind and no detector class — the sin says what is wrong, and the checklist
// the run writes carries the paths and the rule names for the agent that fixes them.
func (r Report) Said() string {
	var lines []string
	files := map[string]bool{}

	counts := map[string]int{}

	for _, slug := range r.skills {
		for _, finding := range r.bySkill[slug] {
			files[finding.File] = true
			said := r.saidOf(finding)

			if counts[said] == 0 {
				lines = append(lines, said)
			}

			counts[said]++
		}
	}

	if len(lines) == 0 {
		return ""
	}

	for at, said := range lines {
		if counts[said] > 1 {
			lines[at] = said + " ×" + strconv.Itoa(counts[said])
		}
	}

	return strings.Join(append(lines, "", natural.Counted(r.total, "sin")+" in "+natural.Counted(len(files), "file")), "\n")
}

// saidOf is one finding said plainly: the file and the line, what is declared there, and the sin itself.
func (r Report) saidOf(finding engine.Finding) string {
	said := filepath.Base(finding.File)
	if _, at, numbered := strings.Cut(finding.Location, ":"); numbered && at != "" {
		said += ", line " + at
	}

	if subject := subjectOf(finding.Scope); subject != "" {
		said += ": " + subject + ", " + r.sinSaid(finding)

		return said
	}

	return said + ": " + r.sinSaid(finding)
}

// sinSaid is what the sin is in a few words: the opening clause of the description its definition carries, which
// is the symptom itself, else its own name. A definition goes on to say why it is a sin and what to do about it,
// and that belongs in the skill the finding names, not in a line on a card.
func (r Report) sinSaid(finding engine.Finding) string {
	says := r.says[finding.Sin]
	if says == "" {
		return finding.Sin
	}

	for _, stop := range []string{" — ", ", so ", ". ", "; "} {
		if clause, _, found := strings.Cut(says, stop); found {
			says = clause
		}
	}

	return shortened(strings.TrimSuffix(strings.ToLower(says[:1])+says[1:], "."))
}

// saidWidth is how long a line's words may run before they are cut at a word: a card shows a line, not a
// paragraph, and a description that goes on says the rest in the skill the finding names.
const saidWidth = 72

// shortened cuts the words at the last word inside saidWidth, saying with an ellipsis that there is more.
func shortened(says string) string {
	if len(says) <= saidWidth {
		return says
	}

	cut := strings.LastIndex(says[:saidWidth], " ")
	if cut < saidWidth/2 {
		cut = saidWidth
	}

	return strings.TrimRight(says[:cut], " ,;(") + "…"
}

// subjectOf is what a scope declares, less the kind of node it is: `FunctionDef oldest_line_at` is said as
// `oldest_line_at`, because a reader acts on the name and the kind is already plain from the code. A scope that
// is a piece of code rather than a name — the condition an unnamed-condition finding is about — says nothing a
// line like this can carry, so it is left out and the sin speaks for itself.
func subjectOf(scope string) string {
	_, named, kinded := strings.Cut(scope, " ")
	if !kinded || named == "" || strings.ContainsAny(named, " `'\"()[]{}=<>$") {
		return ""
	}

	return named
}

func (r Report) relative(location string) string {
	if relative, under := strings.CutPrefix(location, r.path+"/"); under {
		return relative
	}

	return location
}

func hasCustom(findings []engine.Finding) bool {
	for _, finding := range findings {
		if finding.Custom {
			return true
		}
	}

	return false
}

// commandsFor are the commands for the sins among the findings, one per sin, in first-seen order.
func commandsFor(findings []engine.Finding, commands map[string]string) []string {
	var listed []string
	seen := map[string]bool{}

	for _, finding := range findings {
		if command, has := commands[finding.Sin]; has && !seen[finding.Sin] {
			seen[finding.Sin] = true
			listed = append(listed, command)
		}
	}

	return listed
}

// Skipped are the rules that broke and could not run, and the bridges that stopped before they read every file.
type Skipped []string

// skippedConsequence is what a skipped rule costs the run.
const skippedConsequence = "This run does NOT judge what they judge — its verdict is " +
	"incomplete until they are fixed. Their failure was printed to STDERR when it happened."

// IsEmpty says whether every rule ran.
func (s Skipped) IsEmpty() bool {
	return len(s) == 0
}

// Console names the skipped rules for the terminal.
func (s Skipped) Console() string {
	if s.IsEmpty() {
		return ""
	}

	return "\n\033[31m✗ " + s.headline() + "\033[0m\n\033[2m  " + skippedConsequence + "\033[0m"
}

// Markdown names the skipped rules at the top of the checklist.
func (s Skipped) Markdown() string {
	if s.IsEmpty() {
		return ""
	}

	return "> ⚠️ **" + s.headline() + "**\n> " + skippedConsequence + "\n\n"
}

func (s Skipped) headline() string {
	parts := "parts"
	if len(s) == 1 {
		parts = "part"
	}

	return strconv.Itoa(len(s)) + " " + parts + " of the run could not finish: " + strings.Join(s, ", ")
}
