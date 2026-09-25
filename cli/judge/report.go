package judge

import (
	"sort"
	"strconv"
	"strings"

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

	skills := "skills"
	if len(r.skills) == 1 {
		skills = "skill"
	}

	lines = append(lines, "\n\033[1m"+strconv.Itoa(r.total)+" sins\033[0m across "+strconv.Itoa(len(r.skills))+" "+skills+".")

	if !r.skipped.IsEmpty() {
		lines = append(lines, r.skipped.Console())
	}

	lines = append(lines,
		"\033[2m↳ the rule above all: trace each sin to where the value is BORN and fix it THERE — read fix-at-the-source.\033[0m",
		"\033[2m↳ don't recognise a rule above? `vendor/bin/commandments info <sin>` — what it flags, why it is a sin, the fix, an example.\033[0m",
	)

	return strings.Join(lines, "\n")
}

// Checklist is the report as the worklist an agent works down, deleting each line as it fixes the sin.
func (r Report) Checklist() string {
	var out strings.Builder

	out.WriteString("# Code Commandments — " + strconv.Itoa(r.total) + " sins to fix\n\n" + r.skipped.Markdown() + checklistPreamble)

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

	return out.String()
}

// checklistPreamble is how the checklist tells its reader to work it.
const checklistPreamble = "> 🔱 **The rule above all — `fix-at-the-source`.** Every sin below is a SYMPTOM. " +
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
	"`vendor/bin/commandments info <sin>` first — it prints what the rule flags, WHY it is a " +
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

// Skipped are the rules that broke and could not run.
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
	rules := "rules"
	if len(s) == 1 {
		rules = "rule"
	}

	return strconv.Itoa(len(s)) + " " + rules + " could not run: " + strings.Join(s, ", ")
}
