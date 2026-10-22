package hooks

import (
	"bytes"
	"encoding/json"
	"io"
	"os"
	"os/exec"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

const (
	// protocol4 is the version of the agent journal's plugin protocol that journal4-hook speaks.
	protocol4 = 1
	// judging marks the run that judges an event after the answer for it went out.
	judging = "COMMANDMENTS_JUDGING"
)

// hooksOf4 is each event version 4 of the journal names by the hook it stands for. A command of a shell line and a
// file a tool changed are each a moment of their own: the journal cuts the chain and records the edit before it
// tells us, so `command_starting` IS the moment before a command runs and `file_edited` the moment after a file
// changed, one per file. `tool_finished` stands for no hook of ours any more — judging it as well would judge every
// edit twice.
var hooksOf4 = map[string]string{
	"command_starting": "PreToolUse",
	"file_edited":      "PostToolUse",
	"tool_starting":    "PreToolUse",
	"idle":             "Stop",
	"session_started":  "SessionStart",
	"subagent_started": "SubagentStart",
	"prompt_submitted": "UserPromptSubmit",
	"compacting":       "PreCompact",
}

// Journal4Hook is `journal4-hook`: the entry point version 4 of the agent journal runs for every event the plugin
// hears, one JSON line in and one out.
type Journal4Hook struct{}

// Names are the verbs it answers to.
func (Journal4Hook) Names() []string {
	return []string{"journal4-hook"}
}

// Help documents it.
func (Journal4Hook) Help() help.Help {
	return help.Of("Version 4 of the agent journal's entry point — reads one event line from stdin and answers it: the held event (a command about to run) with the gates alone, from what they already know, and every other with what its handlers found, since the journal hears those after its own reply.").
		Form("journal4-hook", "answer the event line on stdin (run by the journal plugin; you rarely run this by hand)").
		Note("Judging happens on `file_edited`, one event per file a tool changed, and what it finds is written to the queue the journal drains at the agent's next hook. A journal that gives the plugin no folder of its own drains no queue, and then every event is answered in full, as before a queue existed.").
		In(help.Hooks)
}

// Run answers the event on stdin. The event the journal may HOLD is answered with nothing worked out, and judged in
// a run of its own that writes to the queue; every other event is heard after the journal's reply, so its answer may
// carry everything found without anything waiting on it. A change of sixty files costs the held event's answer what a
// change of one does.
func (Journal4Hook) Run(in *cli.Input, console cli.Console) (int, error) {
	raw, _ := io.ReadAll(os.Stdin)
	var line map[string]any
	if json.Unmarshal(bytes.TrimSpace(raw), &line) != nil || line == nil {
		console.Write("\n")

		return 0, nil
	}

	if line["event"] == "plugin.updated" {
		said, code, err := JournalConfig{}.Apply(in, console)
		if err == nil && code == 0 {
			console.Write(Configured4(said) + "\n")
		}

		return code, err
	}

	if os.Getenv(judging) != "" {
		Judge4Into(line, os.Getenv(pluginData))

		return 0, nil
	}

	// A worker's merge is held while its own changes are judged: the one event where our answer still decides
	// whether work lands, so it is answered here and in full, never from the queue.
	if asText(line["event"]) == mergeEvent {
		console.Write(MergeHeld(line) + "\n")

		return 0, nil
	}

	console.Write(Answer4Now(line) + "\n")
	if asText(line["event"]) == heldEvent {
		judge4Detached(raw, line)
	}

	return 0, nil
}

// heldEvent is the one event the journal asks the plugin before a command runs, inside a window of a few
// milliseconds. Only the gates answer there, each from what it already knows: a judge cannot answer in time, and a
// judge the journal waits on is the fault this whole path exists to keep away.
const heldEvent = "command_starting"

// Answer4Now is the answer the journal is handed. The held event is answered with the gates alone, since a refusal
// there is the only thing that can still stop a command; what else that moment was worth goes to the queue. Every
// other event is heard AFTER the journal's reply, so nothing waits on its answer and it carries everything found. A
// journal that named the plugin no folder of its own drains no queue, and then even the held event is answered in
// full, as it was before a queue existed.
func Answer4Now(line map[string]any) string {
	moment, known := Moment4Of(line)
	if !known {
		return ""
	}
	if _, queued := Queue4In(os.Getenv(pluginData)); !queued {
		return Answer4Of(line)
	}
	if asText(line["event"]) != heldEvent {
		return Answer4Of(line)
	}

	return answerWith(Stamped(moment), isGate, false).Line4()
}

// Judge4Into works the event out, every handler of it, and writes what it found to the plugin's queue, so the sins
// reach the agent on its next hook. Nothing waits on this: the answer went out before it started.
func Judge4Into(line map[string]any, folder string) {
	queue, queued := Queue4In(folder)
	if !queued {
		return
	}

	moment, known := Moment4Of(line)
	if !known {
		return
	}

	// The gates answered on the way out; what is left is everything else the moment was worth.
	_ = queue.Tell(answerWith(Stamped(moment), not(isGate), true), asText(line["environment"]))
}

// judge4Detached starts that run, in a session of its own so it outlives this one. It reads the event from a file it
// inherits and never from a pipe: a pipe is fed by this process, which answers and ends at once, so the run would
// read nothing and judge nothing. An event that stands for no hook starts no run at all.
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

// Answer4Of is version 4's answer to one event line: every handler run on the moment it stands for, its answer said
// in the line version 4 reads. An event that stands for no hook is allowed and nothing more.
func Answer4Of(line map[string]any) string {
	moment, known := Moment4Of(line)
	if !known {
		return ""
	}

	return answerWith(Stamped(moment), every, true).Line4()
}

// Moment4Of is the event line as the moment version 3 of the journal handed the plugin, so every handler reads it
// as it always has.
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

	if file := asText(firstOf(input["file_path"], input["path"], input["notebook_path"], data["path"])); file != "" {
		tool["file"] = file
	}

	// A command of a shell line is a shell command however the tool was named, and a file the journal recorded an
	// edit of is a write: the handlers read the moment and never the journal's own words for it.
	switch asText(line["event"]) {
	case "command_starting":
		tool["name"] = "Bash"
	case "file_edited":
		tool["name"] = "Write"
	}

	agent := map[string]any{"session": line["session"]}
	if project, named := os.LookupEnv("JOURNAL_ROOT"); named {
		agent["cwd"] = project
	}

	// The event's own data rides along: the edit the journal already worked out is in it, and a judge that reads it
	// never asks git for the lines nor the disk for the file.
	return map[string]any{"event": "hook." + hook, "agent": agent, "tool": tool, "env": line["environment"], "data": data}, true
}

// Line4 is the answer as version 4 of the journal reads it: a refusal with the advice beside its reason, or the
// advice told; and one card per file whose sins changed, under the file's own key, so a later judging of that file
// rewrites the card where it stands and the file left clean settles it green.
func (a JournalAnswer) Line4() string {
	var out bytes.Buffer
	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(a.Shape4())

	return string(bytes.TrimSuffix(out.Bytes(), []byte("\n")))
}

// Shape4 is the answer as version 4 reads it, before it is written: the line the journal is handed and the file the
// queue keeps are the same shape, so they are built in one place.
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
		card := map[string]any{"kind": raise.Event, "key": raise.Key, "label": raise.Brief}
		if raise.Detail != "" {
			card["detail"] = raise.Detail
		}
		cards = append(cards, card)
	}

	if len(cards) > 0 {
		answer["cards"] = cards
	}

	return answer
}

// Configured4 is version 4's answer once the settings are applied: a card saying what changed, under one key so the
// latest stands in place of the one before; nothing when nothing changed.
func Configured4(said []string) string {
	answer := map[string]any{"v": protocol4}
	if len(said) > 0 {
		answer["cards"] = []map[string]any{{"kind": "configured", "key": "settings", "label": strings.Join(said, "; ")}}
	}

	text, _ := json.Marshal(answer)

	return string(text)
}
