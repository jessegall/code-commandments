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
}

var EventKinds = []EventKind{
	{Key: heldEvent, Names: []string{heldEvent}, Hook: "PreToolUse", JudgedLater: true, AnsweredHere: true},
	{Key: editedEvent, Names: []string{editedEvent}, Hook: "PostToolUse", JudgedLater: true, Edits: true},
	{Key: "tool_starting", Names: []string{"tool_starting"}, Hook: "PreToolUse"},
	{Key: idleEvent, Names: []string{idleEvent}, Hook: "Stop", JudgedLater: true, AnsweredHere: true},
	{Key: tickedEvent, Names: []string{tickedEvent}, Hook: "Stop", JudgedLater: true},
	{Key: "session_started", Names: []string{"session_started"}, Hook: "SessionStart"},
	{Key: "subagent_started", Names: []string{"subagent_started"}, Hook: "SubagentStart"},
	{Key: "prompt_submitted", Names: []string{"prompt_submitted"}, Hook: "UserPromptSubmit"},
	{Key: "compacting", Names: []string{"compacting"}, Hook: "PreCompact"},
	{Key: updatedEvent, Names: []string{updatedEvent, "plugin.update"}},
	{Key: deliveringEvent, Names: []string{deliveringEvent, "suites.coding.workers.worker_delivering"}, GuardedBy: "judge_hand_ins", HandedIn: true},
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

// EventKindsJSON is the table as the plugin's script reads it.
func EventKindsJSON() string {
	written, _ := json.MarshalIndent(EventKinds, "", "  ")

	return string(written) + "\n"
}
