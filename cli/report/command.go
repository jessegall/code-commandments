package report

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
)

// hedges are the words that turn a flat claim into a deferral.
var hedges = []string{
	" but ", " later ", " defer", " for now", " out of scope", " needs its own",
	" its own change", " own migration", " own pr", " separate change", " separate pr",
	" not worth", " bigger refactor", " acceptable", " honest enough", " good enough",
	" pre-existing", " baseline", " migration-scoped",
}

// filedBy closes every issue the tool files: the verb that filed it and the release it ran, so a report is always
// read against the code that made it.
func filedBy(verb, version string) string {
	return "\n_Filed via `commandments " + verb + "` from a consumer project, on code-commandments " + version + "._\n"
}

// Command is `report`: a bug in the tool, or a finding that is a false positive. Version is the release it runs.
type Command struct {
	Version string
}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"report"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("File a GitHub issue about code-commandments itself (via `gh`) — a false positive, a wrong rule, or a bug.").
		Form(`report --reason="…" --ref=PATH:LINE`, "a [bug-report] — a defect in the tool, pointing at the code it happened on").
		Form("report follow", "ask GitHub what became of every report this project filed, and what to do about each answer").
		Form("report forget ISSUE", "drop a report once its answer is settled, so its sin is judged as any other again").
		Form(`report accept ISSUE --why="…"`, "keep a report whose fix this project cannot take yet, so nothing asks about it again").
		Form(`report --detector=NAME --reason="…" --ref=PATH:LINE`, "a [detector-report] — this finding is a false positive").
		Option(`--reason="…"`, "what is wrong (required)").
		Option("--ref=PATH:LINE", "the code this is about — repeatable, and PATH:START-END works; the issue names the file and its lines").
		Option("--share-code", "also show the referenced lines (those alone, secrets masked) — only when the project's owner agrees its code may be public").
		Option("--detector=NAME", "the detector that fired — makes this a false-positive report").
		Option(`--best-design="…"`, "the cleanest design you can conceive for the flagged code; REQUIRED by design-smell detectors").
		Option(`--title="…"`, "the issue title (default: the reason's first line)").
		Option("--global", "opt out of --ref when the defect is tied to no file at all (a crash with no args, a CLI-wide bug)").
		Option(`--why="…"`, "with `report accept`: why the answer cannot be acted on, which the dashboard shows beside the row").
		Option("--file=PATH --line=N", "the single-ref alias for --ref").
		Note("Only rules the PACKAGE ships can be reported here. A finding printed as `[Name (custom)]` " +
			"comes from a detector this project wrote into .commandments/custom/ — fix it there; a report " +
			"against it is refused.").
		Note("Filing a detector-report RECORDS the sin it was about in .commandments/reports.json, which is " +
			"tracked: a judge of that line then reads it as reported, with its issue, rather than as a sin to fix, " +
			"and `report follow` says when the report has been answered. The line's own text is kept as a hash, " +
			"never as text, and a second report of a sin already recorded is refused.").
		Note("A report is NOT a deferral. A correct finding must be FIXED, however big the fix — a detector-report " +
			"asserts flatly that the code is right and the rule is wrong, with no hedge. Some detectors (design smells) REQUIRE --best-design: the report is valid only when the flagged code ALREADY IS that best design.")
}

// Run files the report the arguments describe.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	if verb, named := in.FirstArgument(); named && verb == followVerb {
		return c.follow(console), nil
	}

	if verb, named := in.FirstArgument(); named && verb == forgetVerb {
		return c.forget(in, console), nil
	}

	if verb, named := in.FirstArgument(); named && verb == acceptVerb {
		return c.accept(in, console), nil
	}

	reason, given := in.Option("reason")
	refs := references(in)

	if !given {
		return help.Usage(console.Err, c, "--reason is required — say what is wrong."), nil
	}

	if detector, named := in.Option("detector"); named {
		return detectorReport(in, detector, reason, refs, c.Version, console), nil
	}

	if len(refs) == 0 && !in.HasFlag("global") {
		console.Warn("A bug-report must reference the code it's about. Pass one --ref per file involved:",
			`  commandments report --reason="…" --ref=path/to/File.vue:42 --ref=other/File.ts:10-25`,
			"If the bug spans multiple files, you MUST reference EACH of them (repeat --ref).",
			"Only if it genuinely isn't tied to any file, pass --global.")

		return 2, nil
	}

	title, titled := in.Option("title")
	if !titled {
		title = summarise(reason)
	}

	_, code := File("[bug-report] "+title, "**Report:**\n"+reason+"\n"+render(refs, in.HasFlag("share-code"))+filedBy("report", c.Version), console)
	return code, nil
}

func detectorReport(in *cli.Input, detector, reason string, refs []Reference, version string, console cli.Console) int {
	if own, found := ownRule(detector); found {
		console.Warn("✋ "+detector+" is THIS project's own rule — it lives in .commandments/custom/, not in the",
			"  package (.commandments/custom/"+catalog.Name(own)+".json). Nothing upstream can fix it: tighten the rule there",
			"  (a principled reject, never a name list) or drop it from the detectors in .commandments/config.json.")

		return 2
	}

	design, _ := in.Option("best-design")
	designed := strings.Trim(design, " \t\n\r\x00\x0B") != ""

	if !designed && requiresBestDesign(detector) {
		console.Warn("A detector-report against "+detector+" REQUIRES --best-design: the cleanest design you",
			"  can conceive for this code. The litmus — a report is only valid when the flagged code",
			"  ALREADY IS that design. If you can name anything cleaner, THAT design is the owed fix:",
			"  implement it and don't file. Pass it once you've confronted that:",
			`    commandments report --detector=`+detector+` --reason="…" --best-design="…" --ref=PATH:LINE`)

		return 2
	}

	if !designed {
		design = ""
	}

	if hedge, hedged := firstHedge(reason, design); hedged {
		console.Warn(`✋ Your report hedges ("`+hedge+`"). A detector-report is a FLAT assertion: the flagged`,
			"  code is correct, the detector is wrong — full stop. A hedge means the finding is",
			`  REAL and everything after it is scope you own (cost, timing, "its own migration",`,
			"  a cascading refactor are never grounds to file). Implement the fix, or restate the",
			"  reason with no qualifier if the code genuinely already is the cleanest design.")

		return 2
	}

	console.Warn("⚠ A detector-report claims ONE thing: the flagged code is CORRECT under the",
		"  architecture and the detector is wrong. It is NOT a deferral. If the finding",
		"  is right and an honest fix exists — however big (a migration, a cascading",
		"  refactor, many call sites) — you must IMPLEMENT it; a report of a correct",
		"  finding will be closed and the fix will still be owed.")

	body := "**Detector:** `" + detector + "`\n\n" +
		"**Report (why the flagged code is CORRECT and the detector is wrong):**\n" + reason + "\n\n"

	if designed {
		body += "**Cleanest design the reporter can conceive:**\n" + design + "\n\n" +
			"> ⚖️ Maintainer litmus: a valid detector-report needs the flagged code to ALREADY BE the\n" +
			"> cleanest design. If the design above differs from the flagged code at all, THAT design is\n" +
			"> the owed fix — close this report; the fix is still owed.\n"
	}

	filed, code := File("[detector-report] "+detector, body+render(refs, in.HasFlag("share-code"))+filedBy("report", version), console)
	if !filed.Exists() {
		return code
	}

	return remember(detector, reason, refs, filed, console)
}

// remember records what was filed, one row per reference, so a judge of those lines reads the sin as reported
// rather than as a sin to fix and the report can be followed until it is answered.
//
// The issue exists by now, so a refusal to record is said out loud and answered with a failure: a run that said
// nothing would invite a second report of the same sin, and that is a second issue for a maintainer to read.
func remember(detector, reason string, refs []Reference, filed Filed, console cli.Console) int {
	root := workspace.ProjectRoot(workingFolder())
	recorded, refused := 0, 0

	for _, ref := range refs {
		row := reported.Row{
			Detector:   detector,
			Sin:        sinOf(detector),
			File:       absolute(root, ref.Path),
			Line:       ref.Start,
			Reason:     reason,
			Repository: filed.Repository,
			Issue:      filed.Issue,
		}

		held, err := reported.Add(root, row)
		if err == nil {
			recorded++

			continue
		}

		refused++
		console.Warn("The issue is filed as "+filed.URL()+", and "+ref.Label()+" is not recorded as reported:",
			"  "+err.Error(),
			"  Judging that line will call it an active sin again. Nothing here files a second issue for it:",
			"  "+already(held))
	}

	if recorded > 0 {
		console.Write("Recorded as reported in " + reported.Filename + ": " + natural.Counted(recorded, "line") + ", issue " + strconv.Itoa(filed.Issue) + ".\n")

		// The dashboard is drawn from the record, so a report filed changes it and nothing else would redraw it
		// until the next judge.
		if space := workspace.At(root, ""); space.IsJournalDriven() {
			if err := dashboard.Redraw(space); err != nil {
				console.Warn("The reports page could not be drawn again: " + err.Error())
			}
		}
	}

	if refused > 0 {
		return 1
	}

	return 0
}

// sinOf is the sin the rule finds, for whoever reads the record; empty where this build does not know the rule,
// which costs the row nothing, since the rule's own name is what it is keyed by.
func sinOf(detector string) string {
	if rule, found := detectors.Named(detector); found {
		return rule.Sin().Definition().Name
	}

	return ""
}

// already is what to do about a refused row: the issue that already holds the sin, where that is why it was
// refused, and otherwise the line that records it by hand.
func already(held reported.Row) string {
	if held.Issue > 0 {
		return "it is already reported as issue " + strconv.Itoa(held.Issue) + "."
	}

	return "report the line again once it can be read, or leave it: the issue stands either way."
}

// absolute is the reference's path as the record keys it, under the project root.
func absolute(root, path string) string {
	if filepath.IsAbs(path) {
		return path
	}

	return filepath.Join(root, path)
}

// workingFolder is where the command was run, which is how a project is found.
func workingFolder() string {
	cwd, _ := os.Getwd()

	return cwd
}

// requiresBestDesign says whether the detector, or its sin, is a design smell a report must answer with the
// cleanest design.
func requiresBestDesign(name string) bool {
	detector, found := detectors.Named(name)
	if !found {
		return false
	}

	_, marked := detector.(interface{ RequiresBestDesign() })
	_, sinMarked := detector.Sin().(interface{ RequiresBestDesign() })

	return marked || sinMarked
}

// firstHedge is the first hedge any of the texts carries, read as words.
func firstHedge(texts ...string) (string, bool) {
	spaced := strings.NewReplacer(".", " ", ",", " ", ";", " ", ":", " ", "!", " ", "?", " ", "(", " ", ")", " ", `"`, " ", "'", " ", "\n", " ", "\t", " ")

	for _, text := range texts {
		haystack := " " + spaced.Replace(strings.ToLower(text)) + " "

		for _, hedge := range hedges {
			if strings.Contains(haystack, hedge) {
				return strings.TrimSpace(hedge), true
			}
		}
	}

	return "", false
}

// references are the code the report is about: every --ref, then --file with its --line.
func references(in *cli.Input) []Reference {
	values := in.Repeated("ref")

	if file, given := in.Option("file"); given {
		if line, lined := in.Option("line"); lined {
			file += ":" + line
		}

		values = append(values, file)
	}

	var refs []Reference

	for _, value := range values {
		if ref, parsed := ParseReference(value); parsed {
			refs = append(refs, ref)
		}
	}

	return refs
}

// render names each reference by its file and lines, and shows the referenced lines only when the reporter
// shares them: a project's code is never published without its owner's word.
func render(refs []Reference, share bool) string {
	var out strings.Builder

	for _, ref := range refs {
		out.WriteString("\n**Where:** `" + ref.Shown() + "`\n")

		if !share {
			continue
		}

		if code, read := Snippet(ref); read {
			out.WriteString("\n" + code)
		}
	}

	return out.String()
}

// summarise is the reason's first line, cut to 60 characters.
func summarise(reason string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(reason, "\n"), "\n")
	first = strings.Trim(first, " \t\n\r\x00\x0B")

	if utf8.RuneCountInString(first) > 60 {
		return string([]rune(first)[:57]) + "…"
	}

	return first
}

// ownRule is the project's own rule the name means, and whether there is one.
func ownRule(name string) (detectors.Detector, bool) {
	var own []detectors.Detector
	for _, read := range custom.Load(workspace.ProjectRoot(workingFolder())).Rules {
		own = append(own, read)
	}

	return detectors.NamedIn(own, name)
}
