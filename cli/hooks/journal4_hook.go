package hooks

import (
	"bytes"
	"encoding/json"
	"io"
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

// protocol4 is the version of the agent journal's plugin protocol that journal4-hook speaks.
const protocol4 = 1

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
	return help.Of("Version 4 of the agent journal's entry point — reads one event line from stdin, runs every registered handler, and answers in one line: the gate's refusal, the advice told, and each sin found or repented as a card keyed by its file.").
		Form("journal4-hook", "answer the event line on stdin (run by the journal plugin; you rarely run this by hand)").
		In(help.Hooks)
}

// Run answers the event on stdin; settings changed by the user are written into the project's config instead.
func (Journal4Hook) Run(in *cli.Input, console cli.Console) (int, error) {
	raw, _ := io.ReadAll(os.Stdin)
	var line map[string]any
	if json.Unmarshal(bytes.TrimSpace(raw), &line) != nil || line == nil {
		console.Write("\n")

		return 0, nil
	}

	if line["event"] == "plugin.updated" {
		return JournalConfig{}.Run(in, console)
	}

	console.Write(Answer4Of(line) + "\n")

	return 0, nil
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
