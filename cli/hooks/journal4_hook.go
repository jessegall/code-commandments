package hooks

import (
	"strconv"
	"bytes"
	"encoding/json"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

const (
	protocol4 = 1
	judging   = "COMMANDMENTS_JUDGING"
)

const updatedEvent = "plugin.updated"

var theSameEvent = map[string]string{
	"plugin.update":         updatedEvent,
	"worker_worktree.merge": landedEvent,
	"merge_request.create":  requestedEvent,
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

// Journal4Hook is `journal4-hook`: the entry point version 4 of the agent journal runs for every event the plugin hears, one JSON line in and one out.
type Journal4Hook struct{}

func (Journal4Hook) Names() []string {
	return []string{"journal4-hook"}
}

func (Journal4Hook) Help() help.Help {
	return help.Of("Version 4 of the agent journal's entry point — reads one event line from stdin and answers it: a command about to run and a file just changed with the gates alone, from what they already know, and every other event with what its handlers found.").
		Form("journal4-hook", "answer the event line on stdin (run by the journal plugin; you rarely run this by hand)").
		Note("Judging happens on `file_edited`, one event per file a tool changed, and never on the way out: the journal waits on every answer it asks for, and a judge on a loaded machine is slower than the window it allows. What the judge finds is written to the queue the journal drains at the agent's next hook. A journal that gives the plugin no folder of its own drains no queue, and then every event is answered in full, as before a queue existed.").
		In(help.Hooks)
}

func (Journal4Hook) Run(in *cli.Input, console cli.Console) (int, error) {
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

	if os.Getenv(judging) != "" {
		Judge4Into(line, os.Getenv(pluginData))

		return 0, nil
	}

	console.Write(Answer4Now(line) + "\n")
	if keepsTheTreeWarm(asText(line["event"])) {
		WarmsWhatItServes(firstText(os.Getenv(journalRoot), cwdOf()))
	}

	if judgedAfter(asText(line["event"])) {
		judge4Detached(raw, line)
	}

	return 0, nil
}

func Apart4(in *cli.Input, console cli.Console, line map[string]any) (string, bool, int, error) {
	switch namedAsTheKey(asText(line["event"])) {
	case updatedEvent:
		said, code, err := JournalConfig{}.Apply(in, console)

		return Configured4(said), true, code, err
	case deliveringEvent:
		return DeliveryHeld(line), true, 0, nil
	case mergeEvent:
		return MergeHeld(line), true, 0, nil
	case landedEvent:
		return MergeLanded(line), true, 0, nil
	case requestedEvent:
		return MergeRequested(line), true, 0, nil
	}

	return "", false, 0, nil
}

const heldEvent = "command_starting"

const editedEvent = "file_edited"

const (
	tickedEvent  = "ticked"
	idleEvent    = "idle"
	startedEvent = "session_started"
)

func awaited(event string) bool {
	return event == heldEvent || event == editedEvent
}

func keepsTheTreeWarm(event string) bool {
	return event == tickedEvent || event == idleEvent || event == startedEvent
}

func judgedAfter(event string) bool {
	switch event {
	case heldEvent, editedEvent, tickedEvent, idleEvent:
		return true
	}

	return false
}

func Answer4Now(line map[string]any) string {
	moment, known := Moment4Of(line)
	if !known {
		return ""
	}
	if _, queued := Queue4In(os.Getenv(pluginData)); !queued {
		return Answer4Of(line)
	}
	if !judgedAfter(asText(line["event"])) {
		return Answer4Of(line)
	}

	return answerWith(Stamped(moment), isGate, false).Line4()
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
	answer := answerWith(given, not(isGate), true)

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

func judge4Detached(raw []byte, line map[string]any) {
	if _, known := Moment4Of(line); !known {
		return
	}
	if _, queued := Queue4In(os.Getenv(pluginData)); !queued {
		return
	}

	self, err := os.Executable()
	if err != nil {
		return
	}

	event, err := momentFile(raw)
	if err != nil {
		return
	}
	defer event.Close()

	run := exec.Command(self, "journal4-hook")
	run.Stdin = event
	run.Env = append(os.Environ(), judging+"=1")
	detach(run)

	if run.Start() == nil {
		run.Process.Release()
	}
}

func Answer4Of(line map[string]any) string {
	moment, known := Moment4Of(line)
	if !known {
		return ""
	}

	return answerWith(Stamped(moment), every, true).Line4()
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

	told := toldIn(data)

	if file := asText(firstOf(input["file_path"], input["path"], input["notebook_path"], data["path"])); file != "" {
		tool["file"] = within(firstText(told.Root, asText(data["cwd"]), asText(line["cwd"])), os.Getenv(journalRoot), file)
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
	}

	return map[string]any{"event": "hook." + hook, "agent": agent, "tool": tool, "env": line["environment"], "data": data,
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

func (a JournalAnswer) Line4() string {
	var out bytes.Buffer
	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(a.Shape4())

	return string(bytes.TrimSuffix(out.Bytes(), []byte("\n")))
}

func (a JournalAnswer) Shape4() map[string]any {
	answer := map[string]any{"v": protocol4}
	switch {
	case a.Refuse != nil && a.Whisper != nil:
		answer["answer"], answer["reason"] = "refuse", *a.Refuse+"\n\n"+*a.Whisper
	case a.Refuse != nil:
		answer["answer"], answer["reason"] = "refuse", *a.Refuse
	case a.Whisper != nil:
		answer["answer"], answer["tell"] = "tell", *a.Whisper
	}

	var cards []map[string]any
	for _, raise := range a.Raises {
		if raise.Key == "" {
			continue
		}
		card := card4(raise.Event, raise.Key, raise.Brief)
		if raise.Detail != "" {
			card["detail"] = raise.Detail
		}
		cards = append(cards, card)
	}

	if len(cards) > 0 {
		answer["cards"] = cards
	}

	if len(a.Requires) > 0 {
		answer["require_skills"] = a.Requires
	}

	if len(answer) > 1 && a.JudgedMs > 0 {
		answer["judged_ms"] = a.JudgedMs
	}

	if len(answer) > 1 && a.JudgedTotalMs > 0 {
		answer["judged_total_ms"] = a.JudgedTotalMs
	}

	if _, timed := answer["judged_ms"]; timed {
		answer["read_ms"] = a.ReadMs
	}

	return answer
}

func card4(kind, key, label string) map[string]any {
	return map[string]any{"type": kind, "kind": kind, "key": key, "label": label}
}

func Configured4(said []string) string {
	answer := map[string]any{"v": protocol4}
	if len(said) > 0 {
		answer["cards"] = []map[string]any{card4("configured", "settings", strings.Join(said, "; "))}
	}

	text, _ := json.Marshal(answer)

	return string(text)
}
