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

// hooksOf4 is each event version 4 of the journal names by the hook it stands for.
var hooksOf4 = map[string]string{
	"tool_starting":    "PreToolUse",
	"tool_finished":    "PostToolUse",
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
	return help.Of("Version 4 of the agent journal's entry point — reads one event line from stdin and answers at once, with nothing worked out: the plugin holds no hook, so the judging happens in a run of its own and what it finds is written to the queue the journal drains at the agent's next hook, as context and as a card per sin.").
		Form("journal4-hook", "answer the event line on stdin (run by the journal plugin; you rarely run this by hand)").
		Note("A journal that gives the plugin no folder of its own drains no queue, and then the answer carries everything found, as it did before a queue existed.").
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

	console.Write(Answer4Now(line) + "\n")
	if asText(line["event"]) == heldEvent {
		judge4Detached(raw, line)
	}

	return 0, nil
}

// heldEvent is the one event the journal may ask the plugin before the tool runs, inside a window of a few
// milliseconds. Nothing is worked out for it: an answer that has to judge cannot be given in time, and a judge that
// is waited on is the fault this whole path exists to keep away.
const heldEvent = "tool_starting"

// Answer4Now is the answer the journal is handed. The event it may hold is answered with nothing worked out, and
// what it was worth is written to the queue instead; every other event is heard AFTER the journal's reply, so no
// agent waits on it and its answer carries everything found. A journal that named the plugin no folder of its own
// drains no queue, and then even the held event is answered in full, as it was before a queue existed.
func Answer4Now(line map[string]any) string {
	_, queued := Queue4In(os.Getenv(pluginData))
	if asText(line["event"]) == heldEvent && queued {
		return JournalAnswer{}.Line4()
	}

	return Answer4Of(line)
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

	_ = queue.Tell(answerWith(Stamped(moment), every, true))
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

	if command := asText(input["command"]); command != "" {
		tool["command"] = command
	}

	if file := asText(firstOf(input["file_path"], input["path"], input["notebook_path"])); file != "" {
		tool["file"] = file
	}

	agent := map[string]any{"session": line["session"]}
	if project, named := os.LookupEnv("JOURNAL_ROOT"); named {
		agent["cwd"] = project
	}

	return map[string]any{"event": "hook." + hook, "agent": agent, "tool": tool, "env": line["environment"]}, true
}

// Line4 is the answer as version 4 of the journal reads it: a refusal with the advice beside its reason, or the
// advice told; each sin found a card under its key, and each sin repented a card under the same key, which settles the
// first where it stands and says which sin it was.
func (a JournalAnswer) Line4() string {
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
		if raise.Key != "" {
			cards = append(cards, map[string]any{"kind": raise.Event, "key": raise.Key, "label": raise.Brief})
		}
	}

	for _, sin := range a.Repented {
		cards = append(cards, map[string]any{"kind": "sin-resolved", "key": sin.Key, "label": "Repented: " + sin.Shown})
	}

	if len(cards) > 0 {
		answer["cards"] = cards
	}

	var out bytes.Buffer
	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(answer)

	return string(bytes.TrimSuffix(out.Bytes(), []byte("\n")))
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
