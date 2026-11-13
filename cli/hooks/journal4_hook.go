package hooks

import (
	"bytes"
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

const (
	judgeEventOption = "judge-event"
)

const updatedEvent = "plugin.updated"

var theSameEvent = map[string]string{
	"plugin.update":         updatedEvent,
	"worker_worktree.merge": landedEvent,
	"merge_request.create":  requestedEvent,

	"suites.coding.workers.worker_delivering": deliveringEvent,
}

func namedAsTheKey(event string) string {
	if same, known := theSameEvent[event]; known {
		return same
	}

	return event
}

var hooksOf4 = map[string]string{
	"command_starting": "PreToolUse",
	"file_edited":      "PostToolUse",
	"tool_starting":    "PreToolUse",
	"idle":             "Stop",
	"session_started":  "SessionStart",
	"subagent_started": "SubagentStart",
	"prompt_submitted": "UserPromptSubmit",
	"compacting":       "PreCompact",
	"ticked":           "Stop",
}

// PluginHook is `plugin-hook`: the program the agent journal's plugin runs for every event it hears, one JSON line in and one out.
type PluginHook struct{}

func (PluginHook) Names() []string {
	return []string{"plugin-hook"}
}

func (PluginHook) Help() help.Help {
	return help.Of("The agent journal plugin's program — reads one event line from stdin and answers it at once: an edit, a command and a stop with nothing, and every other event with what its handlers found.").
		Form("plugin-hook", "answer the event line on stdin (run by the journal plugin; you rarely run this by hand)").
		Option("--"+judgeEventOption+"=FILE", "judge the event kept in FILE, then remove it: the run the plugin's script (.journal-plugin/hook.py) queues through `journal plugin run --kind judge`").
		Note("Nothing is judged on the way out, and nothing is refused. The plugin's script (.journal-plugin/hook.py) decides which events are judged later, gathers an agent's edits and queues the run that judges them; this program answers each event in its own fields, which the script puts in the journal's words, and leaves what a later judging finds in the plugin's told folder for the script to queue. A journal that gives the plugin no folder of its own drains no queue, and then every event is answered in full.").
		In(help.Hooks)
}

func (PluginHook) Run(in *cli.Input, console cli.Console) (int, error) {
	if event, judges := in.Option(judgeEventOption); judges {
		judgeTheEventIn(event, console)

		return 0, nil
	}

	raw, _ := io.ReadAll(os.Stdin)
	var line map[string]any
	if json.Unmarshal(bytes.TrimSpace(raw), &line) != nil || line == nil {
		console.Write("\n")

		return 0, nil
	}

	if text, apart, code, err := Apart4(in, console, line); apart {
		if err == nil && code == 0 {
			console.Write(text + "\n")
		}

		return code, err
	}

	console.Write(Answer4Now(line) + "\n")

	return 0, nil
}

func Apart4(in *cli.Input, console cli.Console, line map[string]any) (string, bool, int, error) {
	switch namedAsTheKey(asText(line["event"])) {
	case updatedEvent:
		said, code, err := JournalConfig{}.Apply(in, console)

		return Configured4(said), true, code, err
	case deliveringEvent:
		return DeliveryHeld(line), true, 0, nil
	case requestedEvent:
		return MergeRequested(line), true, 0, nil
	case cutEvent:
		return WorktreeCut(line), true, 0, nil
	case tickedEvent:
		cleanEveryWorktree()
	case editedEvent:
		keepEditOf(line)
	}

	return "", false, 0, nil
}

const heldEvent = "command_starting"

const editedEvent = "file_edited"

const (
	tickedEvent = "ticked"
	idleEvent   = "idle"
)

func judgedAfter(event string) bool {
	switch event {
	case heldEvent, editedEvent, tickedEvent, idleEvent:
		return true
	}

	return false
}

func Answer4Now(line map[string]any) string {
	if _, known := Moment4Of(line); !known {
		return ""
	}
	if _, queued := Queue4In(os.Getenv(pluginData)); !queued {
		return Answer4Of(line)
	}
	if !judgedAfter(asText(line["event"])) {
		return Answer4Of(line)
	}

	return JournalAnswer{}.Line()
}

func Judge4Into(line map[string]any, folder string) {
	queue, queued := Queue4In(folder)
	if !queued {
		return
	}

	moment, known := Moment4Of(line)
	if !known {
		return
	}

	given := Stamped(moment)
	window := EditWindowIn(folder, MomentOf(given).Agent)
	if asText(line["event"]) == editedEvent {
		given, _ = gatheredIn(window, given)
	}

	defer rememberedAround(servedRoot(), func(...string) {})()

	answer := answerWith(given)

	data, _ := line["data"].(map[string]any)
	if gone(asText(firstOf(data["cwd"], line["cwd"]))) {
		return
	}

	_ = queue.Tell(answer, asText(line["environment"]), MomentOf(given).Agent)
}

func gone(cwd string) bool {
	if cwd == "" {
		return false
	}

	info, err := os.Stat(cwd)

	return err != nil || !info.IsDir()
}

func gatheredIn(window EditWindow, given map[string]any) (map[string]any, [][]byte) {
	if !window.IsOpen() {
		return given, nil
	}

	time.Sleep(gatheredFor(given))

	edits := window.Close()
	var batch []asked
	for _, edit := range edits {
		var line map[string]any
		if json.Unmarshal(edit, &line) != nil {
			continue
		}

		if moment, known := Moment4Of(line); known {
			batch = append(batch, asked{given: Stamped(moment)})
		}
	}

	if len(batch) == 0 {
		return given, edits
	}

	return batched(batch).given, edits
}

func judgeTheEventIn(path string, console cli.Console) {
	raw, err := os.ReadFile(path)
	os.Remove(path)

	var line map[string]any
	if err != nil || json.Unmarshal(bytes.TrimSpace(raw), &line) != nil || line == nil {
		return
	}

	switch namedAsTheKey(asText(line["event"])) {
	case landedEvent:
		tellsOfTheMerge(line)
	case deliveringEvent:
		defer rememberedAround(servedRoot(), console.Warn)()
		mergeOf(line).judgesAfterwards()
	default:
		Judge4Into(line, os.Getenv(pluginData))
	}
}

func tellsOfTheMerge(line map[string]any) {
	said := landedSaid(line)
	queue, queued := Queue4In(os.Getenv(pluginData))
	if said == "" || !queued {
		return
	}

	_ = queue.Tell(JournalAnswer{Whisper: &said}, asText(line["environment"]), asAgent(line["agent"]))
}

func Answer4Of(line map[string]any) string {
	moment, known := Moment4Of(line)
	if !known {
		return ""
	}

	return answerWith(Stamped(moment)).Line()
}

func Moment4Of(line map[string]any) (map[string]any, bool) {
	hook, known := hooksOf4[asText(line["event"])]
	if !known {
		return nil, false
	}

	data, _ := line["data"].(map[string]any)
	input, _ := data["input"].(map[string]any)
	tool := map[string]any{}
	if name := asText(data["tool"]); name != "" {
		tool["name"] = name
	}

	if command := asText(firstOf(input["command"], data["text"])); command != "" {
		tool["command"] = command
	}

	told := toldOn(line, data)

	if file := asText(firstOf(input["file_path"], input["path"], input["notebook_path"], data["path"])); file != "" {
		tool["file"] = within(firstText(asText(data["cwd"]), told.Root, asText(line["cwd"])), os.Getenv(journalRoot), file)
	}

	switch asText(line["event"]) {
	case "command_starting":
		tool["name"] = "Bash"
	case "file_edited":
		tool["name"] = "Write"
	}

	agent := map[string]any{"session": sessionOf(line), "id": line["agent"], "role": firstOf(line["role"], data["role"])}
	if project, named := os.LookupEnv(journalRoot); named {
		agent["cwd"] = project
	}

	if file := asText(tool["file"]); file != "" {
		where, said := told.rootOrDerived(checkoutOf(asText(agent["cwd"]), file), file)
		agent["cwd"] = where
		agent["checkout_said"] = said
	} else if told.Root != "" {
		agent["cwd"] = firstText(asText(data["cwd"]), told.Root)
		agent["checkout_said"] = "the line"
	}

	return map[string]any{"event": "hook." + hook, "agent": agent, "tool": tool, "env": line["environment"], "checkout": line["checkout"], "data": data,
		"settings": line["settings"]}, true
}

func sessionOf(line map[string]any) string {
	if session := asText(line["session"]); session != "" {
		return session
	}

	if agent := asAgent(line["agent"]); agent != 0 {
		return "agent-" + strconv.Itoa(agent)
	}

	return ""
}

func within(folder, project, file string) string {
	if strings.HasPrefix(file, "/") {
		return file
	}

	for _, under := range []string{folder, withoutLinks(folder), project, withoutLinks(project)} {
		if under == "" {
			continue
		}

		joined := filepath.Join(under, file)
		if info, err := os.Stat(joined); err == nil && info.Mode().IsRegular() {
			return joined
		}
	}

	return file
}

func withoutLinks(folder string) string {
	if folder == "" {
		return ""
	}

	real, err := filepath.EvalSymlinks(folder)
	if err != nil || real == folder {
		return ""
	}

	return real
}

func (a JournalAnswer) Line() string {
	var out bytes.Buffer
	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(a.Fields())

	return string(bytes.TrimSuffix(out.Bytes(), []byte("\n")))
}

// Fields is the answer as the plugin's script reads it: what to refuse, what to tell, the cards to raise, the skills to
// require and how long the judging took. The script shapes it into the journal's answer, so nothing here knows the
// journal's words for one.
func (a JournalAnswer) Fields() map[string]any {
	fields := map[string]any{}
	if a.Refuse != nil {
		fields["refuse"] = *a.Refuse
	}

	if a.Whisper != nil {
		fields["whisper"] = *a.Whisper
	}

	var raised []map[string]any
	for _, raise := range a.Raises {
		if raise.Key == "" {
			continue
		}

		entry := map[string]any{"event": raise.Event, "key": raise.Key, "brief": raise.Brief}
		if raise.Detail != "" {
			entry["detail"] = raise.Detail
		}
		raised = append(raised, entry)
	}

	if len(raised) > 0 {
		fields["raises"] = raised
	}

	if len(a.Requires) > 0 {
		fields["requires"] = a.Requires
	}

	if len(fields) > 0 && a.JudgedMs > 0 {
		fields["judged_ms"], fields["read_ms"] = a.JudgedMs, a.ReadMs
		if a.JudgedTotalMs > 0 {
			fields["judged_total_ms"] = a.JudgedTotalMs
		}
	}

	return fields
}

func Configured4(said []string) string {
	if len(said) == 0 {
		return JournalAnswer{}.Line()
	}

	return JournalAnswer{Raises: []Raise{{Event: "configured", Key: "settings", Brief: strings.Join(said, "; ")}}}.Line()
}
