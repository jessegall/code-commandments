// Package hooks is the hook suite Claude Code runs: one payload read from stdin, every handler the project keeps asked about it, and their answers merged into the one response the harness reads.
package hooks

import (
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var settledStatuses = []string{"completed", "done", "failed", "cancelled", "canceled", "error"}

// Event is one moment the harness reports: its payload, and the project it happened in.
type Event struct {
	payload map[string]any
	Root    string
}

const receivedKey = "commandments_received"

const markedKey = "commandments_file_mark"

func Stamped(payload map[string]any) map[string]any {
	payload[receivedKey] = strconv.FormatInt(time.Now().UnixNano(), 10)

	if mark := warm.Mark(fileNamedIn(payload)); mark != "" {
		payload[markedKey] = mark
	}

	return payload
}

func fileNamedIn(payload map[string]any) string {
	if named := MomentOf(payload).File; named != nil && *named != "" {
		return *named
	}

	return NewEvent(payload, "").FilePath()
}

func sinceStamp(payload map[string]any) int64 {
	stamp := stampOf(payload)
	if stamp == 0 {
		return 0
	}

	return milliseconds(time.Duration(time.Now().UnixNano() - stamp))
}

func heldFor(payload map[string]any, judged int64) int64 {
	return max(sinceStamp(payload), judged)
}

func milliseconds(spent time.Duration) int64 {
	return max(1, spent.Milliseconds())
}

func stampOf(payload map[string]any) int64 {
	stamp, _ := payload[receivedKey].(string)
	nanoseconds, _ := strconv.ParseInt(stamp, 10, 64)

	return nanoseconds
}

func (e Event) Received() time.Time {
	if stamp := stampOf(e.payload); stamp != 0 {
		return time.Unix(0, stamp)
	}

	return time.Now()
}

func NewEvent(payload map[string]any, root string) Event {
	if payload == nil {
		payload = map[string]any{}
	}

	return Event{payload, root}
}

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

func (e Event) Name() string {
	return e.text("hook_event_name")
}

func (e Event) SessionID() string {
	return e.text("session_id")
}

func (e Event) Workspace() workspace.Workspace {
	return workspace.At(e.Root, e.SessionID())
}

func (e Event) SessionWorkspace() workspace.Workspace {
	return workspace.OfSession(e.Root, e.SessionID())
}

func (e Event) AgentID() string {
	return e.text("agent_id")
}

func (e Event) AgentType() string {
	return e.text("agent_type")
}

func (e Event) IsSubagent() bool {
	if e.Name() == "SubagentStop" {
		return false
	}

	return e.text("agent_id") != "" || e.text("agent_type") != ""
}

func (e Event) IsGitCommit() bool {
	command := e.Command()

	return e.IsTool("Bash") && strings.Contains(command, "git commit") &&
		!strings.Contains(command, "commit-graph") && !strings.Contains(command, "--dry-run")
}

func (e Event) IsPlanMode() bool {
	return e.text("permission_mode") == "plan"
}

func (e Event) Tool() string {
	return e.text("tool_name")
}

func (e Event) IsTool(tool string) bool {
	return e.Tool() == tool
}

func (e Event) Source() string {
	return e.text("source")
}

func (e Event) TranscriptPath() string {
	return e.text("transcript_path")
}

func (e Event) Trigger() string {
	return e.text("trigger")
}

func (e Event) CompactSummary() string {
	return e.text("compact_summary")
}

func (e Event) LastAssistantMessage() string {
	return e.text("last_assistant_message")
}

func (e Event) Prompt() string {
	return e.text("prompt")
}

func (e Event) Cwd() string {
	if cwd, isText := e.payload["cwd"].(string); isText && cwd != "" {
		return cwd
	}

	return e.Root
}

func (e Event) Command() string {
	return e.input("command")
}

func (e Event) Cut() string {
	return asText(e.payload[cutKey])
}

func (e Event) FilePath() string {
	return e.input("file_path")
}

func (e Event) WrittenFiles() []string {
	if batch := batchedIn(e.payload); len(batch) > 0 {
		return batch
	}

	if file := e.FilePath(); file != "" {
		return []string{file}
	}

	return nil
}

func (e Event) OnlyRemovedLinesOf(file string) bool {
	lines, carried := batchedLinesOf(e.payload, file)
	if !carried {
		lines, carried = ChangedIn(e.payload)
	}

	return carried && lines.None()
}

func (e Event) ChangedLines(file string) git.ChangedLines {
	if lines, batched := batchedLinesOf(e.payload, file); batched {
		return lines
	}

	changed, carried := ChangedIn(e.payload)
	if carried && e.FilePath() != "" && strings.HasSuffix(filepath.Clean(file), filepath.Clean(e.FilePath())) && !writtenSince(e.payload) {
		return changed
	}

	return git.ChangedLinesOf(checkoutOf(e.Root, file), file)
}

func writtenSince(payload map[string]any) bool {
	was, marked := payload[markedKey].(string)
	if !marked {
		return false
	}

	return warm.Mark(fileNamedIn(payload)) != was
}

func (e Event) AgentTypeRequested() string {
	return e.input("subagent_type")
}

func (e Event) ModelRequested() string {
	return e.input("model")
}

func (e Event) SeesDispatch() bool {
	input, _ := e.payload["tool_input"].(map[string]any)
	_, has := input["prompt"]

	return has
}

func (e Event) Flag(key string) bool {
	value, isBool := e.payload[key].(bool)

	return isBool && value
}

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

func (e Event) text(key string) string {
	return asText(e.payload[key])
}

func (e Event) input(key string) string {
	input, _ := e.payload["tool_input"].(map[string]any)

	return asText(input[key])
}

func intOf(value any) int {
	switch number := value.(type) {
	case float64:
		return int(number)
	case int:
		return number
	case json.Number:
		read, _ := strconv.Atoi(number.String())

		return read
	case string:
		read, _ := strconv.Atoi(number)

		return read
	}

	return 0
}

func asAgent(value any) int {
	switch typed := value.(type) {
	case int:
		return typed
	case float64:
		return int(typed)
	case json.Number:
		if whole, err := typed.Int64(); err == nil {
			return int(whole)
		}
	}

	return 0
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
