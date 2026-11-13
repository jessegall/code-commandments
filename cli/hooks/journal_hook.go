package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
)

const (
	quietHooks  = "COMMANDMENTS_QUIET_HOOKS"
	pluginData  = "JOURNAL_PLUGIN_DATA"
	advising    = "COMMANDMENTS_ADVISING"
	journalRoot = "JOURNAL_ROOT"
)

func answerWith(given map[string]any) JournalAnswer {
	moment := MomentOf(given)
	if moment.Event == "" {
		return JournalAnswer{}
	}

	if judgedLive(moment) && !liveEditsJudgedAt(moment) {
		return JournalAnswer{}
	}

	cwd := moment.Cwd
	if info, err := os.Stat(cwd); err != nil || !info.IsDir() {
		cwd, _ = os.Getwd()
	}

	if notForThisRole(moment) != "" {
		return JournalAnswer{}
	}

	event := NewEvent(moment.HookPayload(), workspace.ProjectRoot(cwd))
	event.WithoutCLI = workerRoles[moment.Role] && !helpersRunTheCLI(moment)
	workspace.At(event.Root, "").RelocateSessions()
	quiet := quieted()

	var responses []Response

	judging := time.Now()

	for _, hook := range ForProject(event.Root) {
		if !slices.Contains(quiet, hook.Class()) {
			responses = append(responses, Answer(hook, event))
		}
	}

	spent := time.Since(judging)

	merged := Merge(responses)
	answer := answerOf(merged)
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	if !moment.IsPostToolUse() && !moment.IsStop() {
		return answer
	}

	recordJudging(moment, event, merged, answer, spent)

	settlement := settled(moment, event.Root, merged.Activity, merged.Judged, merged.Asked)
	answer.Raises = append(answer.Raises, settlement.Raises(event.Root)...)
	answer.Settles = settlement.Settles()
	answer.Repented = settlement.Repented
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	return answer
}

func recordJudging(moment Moment, event Event, merged Response, answer JournalAnswer, spent time.Duration) {
	folder := bookkeepingIn(event.Root)
	if folder == "" || len(merged.Judged) == 0 || !recorded(event.Root) {
		return
	}

	checkout := checkoutOf(event.Root, moment.Cwd)
	kind := "project"
	if checkout != strings.TrimRight(git.ProjectRoot(event.Root), "/") {
		kind = "worktree"
	}

	named, _ := rootOf(event.Root)
	_, _, held := seenFor(event.Root)

	why := Narrowed("")
	asked, notAsked := 0, 0
	for file, sins := range merged.Asked {
		asked = len(sins)
		notAsked = merged.NotAsked[file]
		why = merged.Narrowed[file]
	}

	Record(folder, Judged{
		At: time.Now(), Event: moment.Event, Checkout: checkout, Kind: kind, Branch: named.branch,
		Held: held, Why: why, Parsed: len(merged.Judged),
		ReadMs: merged.ReadMs, RuleMs: milliseconds(spent) - merged.ReadMs, Asked: asked, NotAsked: notAsked,
		Sins: len(merged.Activity), Dropped: DroppedSoFar(), Memory: MemoryNow(), Said: moment.CheckoutSaid, Files: merged.Judged,
	})
}

func ceilingChosen() int {
	chosen, given := chosenSettings()
	if !given {
		return 0
	}

	said, named := chosen[ceilingKey]
	if !named {
		return 0
	}

	nodes, err := strconv.Atoi(strings.TrimSpace(asText(said)))
	if err != nil || nodes < 0 {
		return 0
	}

	return nodes
}

func notForThisRole(moment Moment) string {
	if moment.Role == "" || !workerRoles[moment.Role] || workerEditsJudgedAt(moment) {
		return ""
	}

	return Unjudged("a "+moment.Role+"'s own edits are not judged",
		"the setting "+workerEditsKey+" or "+workersKey+" is off", "")
}

func workersChose(chosen map[string]any, key string) bool {
	return switchedOn(chosen[workersKey]) && switchedOn(chosen[key])
}

func workerEditsJudgedAt(moment Moment) bool {
	return workersChose(chosenAt(moment), workerEditsKey)
}

func judgedLive(moment Moment) bool {
	return moment.IsPostToolUse() || moment.IsStop()
}

func liveEditsJudgedAt(moment Moment) bool {
	return switchedOn(chosenAt(moment)[liveEditsKey])
}

func chosenAt(moment Moment) map[string]any {
	if len(moment.Settings) > 0 {
		return moment.Settings
	}

	chosen, _ := chosenSettings()

	return chosen
}

func switchedOn(value any) bool {
	if on, isFlag := value.(bool); isFlag {
		return on
	}

	return asText(value) == "true"
}

func settingsOn(given map[string]any) map[string]any {
	settings, _ := given["settings"].(map[string]any)

	return settings
}

func recorded(string) bool {
	chosen, given := chosenSettings()
	if !given {
		return true
	}

	said, named := chosen[recordKey]

	return !named || asText(said) != "false"
}

func settled(moment Moment, root string, marks []SinMark, read []string, asked Asked) Settlement {
	data := os.Getenv(pluginData)
	reconsidered := append(read, named(moment.File)...)

	if data == "" {
		return Forgotten().SettleAsked(root, reconsidered, marks, asked)
	}

	record := AnnouncedIn(data, moment)

	if moment.IsStop() {
		swept, files := sweptMarks(record, root)
		marks = append(marks, swept...)
		reconsidered = append(reconsidered, files...)
	}

	var findings []engine.Finding
	judged := map[string]bool{}

	if moment.File != nil && *moment.File != "" {
		judged[resolved(moment.Cwd, *moment.File)] = true
	}

	for _, mark := range marks {
		findings = append(findings, mark.Finding())
		judged[resolved(moment.Cwd, mark.Match.File())] = true
	}

	dashboard.Record(workspace.At(root, ""), findings, judged)

	return record.SettleAsked(root, reconsidered, marks, asked)
}

func named(file *string) []string {
	if file == nil || *file == "" {
		return nil
	}

	return []string{*file}
}

func resolved(folder, file string) string {
	absolute := file
	if !filepath.IsAbs(file) {
		absolute = filepath.Join(folder, file)
	}

	if real, err := filepath.EvalSymlinks(absolute); err == nil {
		return real
	}

	return file
}

func quieted() []string {
	var quiet []string

	for _, class := range strings.Split(os.Getenv(quietHooks), ",") {
		if class = strings.TrimSpace(class); class != "" {
			quiet = append(quiet, class)
		}
	}

	return quiet
}

// asked is one moment to judge after its event, as a batch of an agent's edits gathers it.
type asked struct {
	given map[string]any
}

const (
	batchedKey      = "commandments_batched"
	batchedLinesKey = "commandments_batched_lines"
)

func batched(batch []asked) asked {
	if len(batch) == 1 {
		return batch[0]
	}

	var files []any
	lines := map[string]any{}
	stands := batch[0]
	named := false

	for _, held := range batch {
		moment := MomentOf(held.given)
		if moment.File == nil || *moment.File == "" {
			continue
		}

		if !named {
			stands, named = held, true
		}

		files = append(files, *moment.File)

		switch {
		case moment.ChangedWhole:
			lines[*moment.File] = map[string]any{"whole": true}
		case len(moment.Changed) > 0:
			written := make([]any, 0, len(moment.Changed))
			for _, line := range moment.Changed {
				written = append(written, line)
			}

			lines[*moment.File] = map[string]any{"lines": written}
		}
	}

	if len(files) > 1 {
		stands.given[batchedKey] = files
		stands.given[batchedLinesKey] = lines
	}

	return stands
}

func batchedIn(payload map[string]any) []string {
	held, batched := payload[batchedKey].([]any)
	if !batched {
		return nil
	}

	var files []string
	for _, file := range held {
		if named := asText(file); named != "" {
			files = append(files, named)
		}
	}

	return files
}

func batchedLinesOf(payload map[string]any, file string) (git.ChangedLines, bool) {
	held, batched := payload[batchedLinesKey].(map[string]any)
	if !batched {
		return git.ChangedLines{}, false
	}

	for named, carried := range held {
		if named != file && !strings.HasSuffix(filepath.Clean(file), filepath.Clean(named)) {
			continue
		}

		return ChangedIn(map[string]any{changedKey: carried})
	}

	return git.ChangedLines{}, false
}

func servedRoot() string {
	return workspace.ProjectRoot(firstText(os.Getenv(journalRoot), cwdOf()))
}
