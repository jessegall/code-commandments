package hooks

import (
	"encoding/json"
	"slices"
)

// EventKind is everything this tool knows of one journal event, stated once: the name both sides use for it, every
// name the journal sends it under, the hook it stands for, and how the plugin's script routes it. The script reads
// the same rows from .journal-plugin/events.json, which `go run ./cli/doc/readme` writes from this table.
type EventKind struct {
	Key   string   `json:"key"`
	Names []string `json:"names"`
	Hook  string   `json:"hook,omitempty"`
	// JudgedLater is an event whose judging runs after it is answered, queued for its agent.
	JudgedLater bool `json:"judged_later,omitempty"`
	// AnsweredHere is an event the script answers itself once it has refused what it refuses.
	AnsweredHere bool `json:"answered_here,omitempty"`
	// Edits is the event of a file the agent edited, gathered into one batch per agent.
	Edits bool `json:"edits,omitempty"`
	// GuardedBy is the setting that holds the event on a judging before it goes through.
	GuardedBy string `json:"guarded_by,omitempty"`
	HandedIn  bool   `json:"handed_in,omitempty"`
	Merged    bool   `json:"merged,omitempty"`
	// Tools is every tool a hook of this tool acts on when the event names one; the script answers any other itself.
	Tools []string `json:"tools,omitempty"`
	// BinaryEveryMinutes is how often the event is passed to the binary at most; the script answers it in between.
	BinaryEveryMinutes int `json:"binary_every_minutes,omitempty"`
	// namesTheTool is an event about whichever tool the agent starts, so only the tools a hook acts on reach the binary.
	namesTheTool bool
}

var EventKinds = []EventKind{
	{Key: heldEvent, Names: []string{heldEvent}, Hook: "PreToolUse", JudgedLater: true, AnsweredHere: true},
	{Key: editedEvent, Names: []string{editedEvent}, Hook: "PostToolUse", JudgedLater: true, Edits: true},
	{Key: "tool_starting", Names: []string{"tool_starting"}, Hook: "PreToolUse", namesTheTool: true},
	{Key: idleEvent, Names: []string{idleEvent}, Hook: "Stop", JudgedLater: true, AnsweredHere: true},
	{Key: tickedEvent, Names: []string{tickedEvent}, Hook: "Stop", JudgedLater: true, BinaryEveryMinutes: 5},
	{Key: "session_started", Names: []string{"session_started"}, Hook: "SessionStart"},
	{Key: "subagent_started", Names: []string{"subagent_started"}, Hook: "SubagentStart"},
	{Key: "prompt_submitted", Names: []string{"prompt_submitted"}, Hook: "UserPromptSubmit"},
	{Key: "compacting", Names: []string{"compacting"}, Hook: "PreCompact"},
	{Key: updatedEvent, Names: []string{updatedEvent, "plugin.update", "core.plugins.plugin_setting.created", "core.plugins.plugin_setting.updated", "core.plugins.plugin_setting.deleted", "plugin_setting.created", "plugin_setting.updated", "plugin_setting.deleted", "plugin_setting.create", "plugin_setting.update", "plugin_setting.delete"}},
	{Key: deliveringEvent, Names: []string{deliveringEvent, "suites.coding.workers.worker_delivering"}, GuardedBy: handInsKey, HandedIn: true},
	{Key: landedEvent, Names: []string{landedEvent, "worker_worktree.merge"}, Merged: true},
	{Key: requestedEvent, Names: []string{requestedEvent, "merge_request.create"}, GuardedBy: "judge_before_merge"},
	{Key: cutEvent, Names: []string{cutEvent}},
}

// kindNamed is the kind the journal sends under this name.
func kindNamed(name string) (EventKind, bool) {
	at := slices.IndexFunc(EventKinds, func(kind EventKind) bool { return slices.Contains(kind.Names, name) })
	if at < 0 {
		return EventKind{}, false
	}

	return EventKinds[at], true
}

// ScriptFacts are what the plugin's script decides by besides the event kinds, stated once here: the setting each
// switch is read under, the roles a worker runs as, how long an agent's edits gather and the folder the answers given
// after their event wait in. The script reads them from
// .journal-plugin/script-facts.json, which `go run ./cli/doc/readme` writes from this table.
type ScriptFacts struct {
	Settings    map[string]string `json:"settings"`
	WorkerRoles []string          `json:"worker_roles"`
	// EditWindow is the seconds an agent's edits gather when the setting names none, and the most it may name.
	EditWindow       float64 `json:"edit_window"`
	EditWindowAtMost float64 `json:"edit_window_at_most"`
	ToldFolder       string  `json:"told_folder"`
}

const (
	editWindowKey     = "judge_edit_window"
	DefaultEditWindow = 5
	EditWindowAtMost  = 60
)

var scriptFacts = ScriptFacts{
	Settings: map[string]string{
		"live_edits": liveEditsKey, "workers": workersKey, "worker_edits": workerEditsKey, "hand_ins": handInsKey,
		"hand_ins_after": handInsAfterKey, "edit_window": editWindowKey, "helpers_cli": helpersCLIKey,
	},
	WorkerRoles:      []string{"worker", "helper", "medior", "senior", "builder"},
	EditWindow:       DefaultEditWindow,
	EditWindowAtMost: EditWindowAtMost,
	ToldFolder:       toldFolder,
}

// ScriptFactsJSON is the table as the plugin's script reads it.
func ScriptFactsJSON() string {
	written, _ := json.MarshalIndent(scriptFacts, "", "  ")

	return string(written) + "\n"
}

// EventKindsJSON is the table as the plugin's script reads it.
func EventKindsJSON() string {
	kinds := slices.Clone(EventKinds)
	for at, kind := range kinds {
		if kind.namesTheTool {
			kinds[at].Tools = toolsActedOn(kind.Hook)
		}
	}
	written, _ := json.MarshalIndent(kinds, "", "  ")

	return string(written) + "\n"
}

// toolsActedOn is every tool a built-in hook acts on at the moment, none when one acts on every tool.
func toolsActedOn(moment string) []string {
	var tools []string
	for _, hook := range Builtins() {
		for _, binding := range hook.Bindings() {
			switch {
			case binding.Event != moment:
			case binding.Matcher == "":
				return nil
			case !slices.Contains(tools, binding.Matcher):
				tools = append(tools, binding.Matcher)
			}
		}
	}
	slices.Sort(tools)

	return tools
}
