// Package hooks is the hook suite Claude Code runs: one payload read from stdin, every handler the project
// keeps asked about it, and their answers merged into the one response the harness reads.
package hooks

import (
	"encoding/json"
	"io"
	"os"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// settledStatuses are the statuses of background work that has finished, one way or another.
var settledStatuses = []string{"completed", "done", "failed", "cancelled", "canceled", "error"}

// Event is one moment the harness reports: its payload, and the project it happened in.
type Event struct {
	payload map[string]any
	Root    string
}

// receivedKey is where a payload carries when the hook was handed it: work done later, off the hook's path, still
// knows when the moment happened.
const receivedKey = "commandments_received"

// Stamped is the payload marked with when the hook was handed it, now.
func Stamped(payload map[string]any) map[string]any {
	payload[receivedKey] = strconv.FormatInt(time.Now().UnixNano(), 10)

	return payload
}

// stampOf is when the hook was handed the payload, in unix nanoseconds; zero when it was not stamped.
func stampOf(payload map[string]any) int64 {
	stamp, _ := payload[receivedKey].(string)
	nanoseconds, _ := strconv.ParseInt(stamp, 10, 64)

	return nanoseconds
}

// Received is when the hook was handed the moment: the stamp it carries, else now, for a hook that runs before
// the moment goes on.
func (e Event) Received() time.Time {
	if stamp := stampOf(e.payload); stamp != 0 {
		return time.Unix(0, stamp)
	}

	return time.Now()
}

// NewEvent is the moment the payload describes, in the project at root.
func NewEvent(payload map[string]any, root string) Event {
	if payload == nil {
		payload = map[string]any{}
	}

	return Event{payload, root}
}

// ReadPayload is the payload on in: empty when in is a terminal or holds no JSON object.
func ReadPayload(in *os.File) map[string]any {
	if info, err := in.Stat(); err != nil || info.Mode()&os.ModeCharDevice != 0 {
		return map[string]any{}
	}

	text, _ := io.ReadAll(in)

	var payload map[string]any
	if json.Unmarshal(text, &payload) != nil || payload == nil {
		return map[string]any{}
	}

	return payload
}

// Name is the event's name: Stop, PreToolUse, SessionStart, ….
func (e Event) Name() string {
	return e.text("hook_event_name")
}

// SessionID is the session the moment belongs to.
func (e Event) SessionID() string {
	return e.text("session_id")
}

// Workspace is the project's workspace for this session.
func (e Event) Workspace() workspace.Workspace {
	return workspace.At(e.Root, e.SessionID())
}

// SessionWorkspace is the workspace of the project the session works in, shared by its worktrees.
func (e Event) SessionWorkspace() workspace.Workspace {
	return workspace.OfSession(e.Root, e.SessionID())
}

// AgentID is the subagent the moment happened in; empty in the main session.
func (e Event) AgentID() string {
	return e.text("agent_id")
}

// AgentType is the subagent's type; empty in the main session.
func (e Event) AgentType() string {
	return e.text("agent_type")
}

// IsSubagent says whether the moment happened inside a subagent. SubagentStop is the one event whose agent
// fields name somebody else, the worker that stopped, while the hook runs in the session that spawned it.
func (e Event) IsSubagent() bool {
	if e.Name() == "SubagentStop" {
		return false
	}

	return e.text("agent_id") != "" || e.text("agent_type") != ""
}

// IsGitCommit says whether the moment is a Bash call that commits.
func (e Event) IsGitCommit() bool {
	command := e.Command()

	return e.IsTool("Bash") && strings.Contains(command, "git commit") &&
		!strings.Contains(command, "commit-graph") && !strings.Contains(command, "--dry-run")
}

// IsPlanMode says whether the session is planning rather than acting.
func (e Event) IsPlanMode() bool {
	return e.text("permission_mode") == "plan"
}

// Tool is the tool the moment is about.
func (e Event) Tool() string {
	return e.text("tool_name")
}

// IsTool says whether the moment is about the tool.
func (e Event) IsTool(tool string) bool {
	return e.Tool() == tool
}

// Source is what started the session: startup, resume, clear, compact.
func (e Event) Source() string {
	return e.text("source")
}

// TranscriptPath is where the session's transcript is.
func (e Event) TranscriptPath() string {
	return e.text("transcript_path")
}

// Trigger is what set off a compaction.
func (e Event) Trigger() string {
	return e.text("trigger")
}

// CompactSummary is the summary a compaction wrote.
func (e Event) CompactSummary() string {
	return e.text("compact_summary")
}

// LastAssistantMessage is the last thing the agent said before it stopped.
func (e Event) LastAssistantMessage() string {
	return e.text("last_assistant_message")
}

// Prompt is what the user submitted.
func (e Event) Prompt() string {
	return e.text("prompt")
}

// Cwd is the folder the moment happened in; the project when the payload names none.
func (e Event) Cwd() string {
	if cwd, isText := e.payload["cwd"].(string); isText && cwd != "" {
		return cwd
	}

	return e.Root
}

// Command is the shell command a Bash call runs.
func (e Event) Command() string {
	return e.input("command")
}

// FilePath is the file a writing tool writes.
func (e Event) FilePath() string {
	return e.input("file_path")
}

// AgentTypeRequested is the subagent type a dispatch asks for.
func (e Event) AgentTypeRequested() string {
	return e.input("subagent_type")
}

// ModelRequested is the model a dispatch asks for.
func (e Event) ModelRequested() string {
	return e.input("model")
}

// SeesDispatch says whether the moment carries a dispatch's prompt.
func (e Event) SeesDispatch() bool {
	input, _ := e.payload["tool_input"].(map[string]any)
	_, has := input["prompt"]

	return has
}

// Flag says whether the payload sets the key to true.
func (e Event) Flag(key string) bool {
	value, isBool := e.payload[key].(bool)

	return isBool && value
}

// HasPendingBackgroundWork says whether any background task is still running.
func (e Event) HasPendingBackgroundWork() bool {
	tasks, isList := e.payload["background_tasks"].([]any)
	if !isList {
		return false
	}

	for _, task := range tasks {
		fields, _ := task.(map[string]any)
		status, _ := fields["status"].(string)

		if !slices.Contains(settledStatuses, status) {
			return true
		}
	}

	return false
}

// text is a payload value as text, as PHP's string cast reads it.
func (e Event) text(key string) string {
	return asText(e.payload[key])
}

func (e Event) input(key string) string {
	input, _ := e.payload["tool_input"].(map[string]any)

	return asText(input[key])
}

func asText(value any) string {
	switch typed := value.(type) {
	case string:
		return typed
	case bool:
		if typed {
			return "1"
		}

		return ""
	case float64:
		text, _ := json.Marshal(typed)

		return string(text)
	default:
		return ""
	}
}
