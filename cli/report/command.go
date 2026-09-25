package report

import (
	"strings"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/detectors"
)

// hedges are the words that turn a flat claim into a deferral.
var hedges = []string{
	" but ", " later ", " defer", " for now", " out of scope", " needs its own",
	" its own change", " own migration", " own pr", " separate change", " separate pr",
	" not worth", " bigger refactor", " acceptable", " honest enough", " good enough",
	" pre-existing", " baseline", " migration-scoped",
}

// footer closes every issue the tool files.
const footer = "\n_Filed via `commandments report` from a consumer project._\n"

// Command is `report`: a bug in the tool, or a finding that is a false positive.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"report"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("File a GitHub issue about code-commandments itself (via `gh`) — a false positive, a wrong rule, or a bug.").
		Form(`report --reason="…" --ref=PATH:LINE`, "a [bug-report] — a defect in the tool, pointing at the code it happened on").
		Form(`report --detector=NAME --reason="…" --ref=PATH:LINE`, "a [detector-report] — this finding is a false positive").
		Option(`--reason="…"`, "what is wrong (required)").
		Option("--ref=PATH:LINE", "the code this is about — repeatable, and PATH:START-END works; the source is read and injected into the issue").
		Option("--detector=NAME", "the detector that fired — makes this a false-positive report").
		Option(`--best-design="…"`, "the cleanest design you can conceive for the flagged code; REQUIRED by design-smell detectors").
		Option(`--title="…"`, "the issue title (default: the reason's first line)").
		Option("--global", "opt out of --ref when the defect is tied to no file at all (a crash with no args, a CLI-wide bug)").
		Option("--file=PATH --line=N", "the single-ref alias for --ref").
		Note("Only rules the PACKAGE ships can be reported here. A finding printed as `[Name (custom)]` " +
			"comes from a detector this project wrote into .commandments/custom/ — fix it there; a report " +
			"against it is refused.").
		Note("A report is NOT a deferral. A correct finding must be FIXED, however big the fix — a detector-report " +
			"asserts flatly that the code is right and the rule is wrong, with no hedge. Some detectors (design smells) REQUIRE --best-design: the report is valid only when the flagged code ALREADY IS that best design.")
}

// Run files the report the arguments describe.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	reason, given := in.Option("reason")
	refs := references(in)

	if !given {
		return help.Usage(console.Err, c, "--reason is required — say what is wrong."), nil
	}

	if detector, named := in.Option("detector"); named {
		return detectorReport(in, detector, reason, refs, console), nil
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

	return File("[bug-report] "+title, "**Report:**\n"+reason+"\n"+render(refs)+footer, console), nil
}

func detectorReport(in *cli.Input, detector, reason string, refs []Reference, console cli.Console) int {
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

	return File("[detector-report] "+detector, body+render(refs)+footer, console)
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

func render(refs []Reference) string {
	var out strings.Builder

	for _, ref := range refs {
		out.WriteString("\n**Where:** `" + ref.Label() + "`\n")

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
