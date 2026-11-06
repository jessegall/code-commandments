// Package hooks is the hook suite Claude Code runs: one payload read from stdin, every handler the project
// keeps asked about it, and their answers merged into the one response the harness reads.
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

// markedKey is where a payload carries what the file it names WAS when the hook was handed it, so work done later
// can tell that file apart from a version written since without asking anyone's clock.
const markedKey = "commandments_file_mark"

// Stamped is the payload marked with when the hook was handed it, now, and with what the file it names was then.
func Stamped(payload map[string]any) map[string]any {
	payload[receivedKey] = strconv.FormatInt(time.Now().UnixNano(), 10)

	if mark := warm.Mark(fileNamedIn(payload)); mark != "" {
		payload[markedKey] = mark
	}

	return payload
}

// fileNamedIn is the file the payload names, in either of the two shapes we are handed one in: the journal's own
// moment, and the harness's hook payload. Both are ours, so both are read; a payload naming no file names none.
func fileNamedIn(payload map[string]any) string {
	if named := MomentOf(payload).File; named != nil && *named != "" {
		return *named
	}

	return NewEvent(payload, "").FilePath()
}

// sinceStamp is how long the plugin has been over this event, in milliseconds, counted from the moment it was
// handed it. Zero where it was never stamped, which is an answer nobody can time rather than one that was free.
func sinceStamp(payload map[string]any) int64 {
	stamp := stampOf(payload)
	if stamp == 0 {
		return 0
	}

	return milliseconds(time.Duration(time.Now().UnixNano() - stamp))
}

// heldFor is how long the plugin held the event, which can never be less than the judging inside it. Where no
// service holds the queue, the event is stamped as its judging begins, so the two measure nearly the same
// interval and rounding alone had the part reading longer than the whole.
func heldFor(payload map[string]any, judged int64) int64 {
	return max(sinceStamp(payload), judged)
}

// milliseconds is the duration as the answer carries it, never nought for work that did happen: a judging too
// quick to measure is one millisecond rather than none, since none is what an untimed answer says.
func milliseconds(spent time.Duration) int64 {
	return max(1, spent.Milliseconds())
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

// ChangedLines are the lines of the file this moment's edit wrote: the ones whoever reported the moment had
// already worked out, when it carries them for this very file, and otherwise the ones git finds between the file
// and HEAD. A sin on a line the edit did not touch is not this edit's doing, which is the whole use of them.
func (e Event) ChangedLines(file string) git.ChangedLines {
	// A moment standing for a batch carries the lines of every edit it stands for, by file: its own named file
	// is only one of them, and the others would otherwise fall back to asking git, which answers nothing about
	// work a worktree has not committed.
	if lines, batched := batchedLinesOf(e.payload, file); batched {
		return lines
	}

	changed, carried := ChangedIn(e.payload)
	if carried && e.FilePath() != "" && strings.HasSuffix(filepath.Clean(file), filepath.Clean(e.FilePath())) && !writtenSince(e.payload) {
		return changed
	}

	return git.ChangedLinesOf(e.Root, file)
}

// writtenSince says the file was written again after this moment was handed to us, which is what two edits of one
// file in quick succession look like. The lines the moment carries are then the lines of a version that is gone:
// the judging reads the file as it NOW is, and marking it with the earlier edit's lines calls a line touched that
// this version never had. git answers about the file as it is, which is the version being judged.
//
// Asked of the file against ITSELF, by the mark we took when we were handed the moment. It used to be asked of the
// file's modification time against our own clock, and those are two clocks: under Docker's virtiofs, and on any
// mount whose timestamps come from elsewhere, a file written BEFORE we stamped reports a time after it, so the
// lines the journal handed over were thrown away for a file nobody had touched.
//
// A payload we could take no mark of claims nothing: its lines stand, as they did before any of this was asked.
func writtenSince(payload map[string]any) bool {
	was, marked := payload[markedKey].(string)
	if !marked {
		return false
	}

	return warm.Mark(fileNamedIn(payload)) != was
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

// intOf is a number the journal wrote, however JSON gave it back.
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

// asAgent is the journal's own agent number, as an event's `agent` carries it: a whole number, however the JSON
// decoder read it, and 0 where the event names none. 0 is no agent, since the journal numbers its agents from 1,
// so an event without one is told apart from an event with one rather than read as the first.
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
