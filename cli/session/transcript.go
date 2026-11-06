// Package session is what the tool knows about the agent sessions working in a project: their transcripts, the folders they keep state in, and the `session` command that names, lists and adopts those folders.
package session

import (
	"bufio"
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const transcripts = "/.claude/projects/"

// Session is one transcript: its id, where it lives, when it last changed, and what it is called.
type Session struct {
	ID   string
	Path string
	At   int64
	Name string
}

func (s Session) Key() string {
	return workspace.KeyFor(s.ID)
}

func Sessions(root string) []Session {
	home := os.Getenv("HOME")
	if home == "" {
		home = "~"
	}

	paths, _ := filepath.Glob(home + transcripts + strings.ReplaceAll(root, "/", "-") + "/*.jsonl")
	var sessions []Session

	for _, path := range paths {
		sessions = append(sessions, Session{
			ID:   strings.TrimSuffix(filepath.Base(path), ".jsonl"),
			Path: path,
			At:   folder.Modified(path),
			Name: Transcript(path).Name(600),
		})
	}

	sort.SliceStable(sessions, func(i, j int) bool {
		return sessions[i].At > sessions[j].At
	})

	return sessions
}

// Category is what kind of line a transcript record is.
type Category string

const (
	Prompt      Category = "prompt"
	Reply       Category = "reply"
	ToolResult  Category = "tool-result"
	Injected    Category = "injected"
	Boundary    Category = "boundary"
	Summary     Category = "summary"
	Bookkeeping Category = "bookkeeping"
)

func (c Category) IsSpeech() bool {
	return c == Prompt || c == Reply
}

var (
	bookkeeping = []string{
		"summary", "last-prompt", "ai-title", "agent-name", "mode", "permission-mode",
		"file-history-snapshot", "file-history-delta", "queue-operation", "pr-link",
	}
	human = []string{"typed", "queued"}
)

const boundary = "compact_boundary"

// Transcript is a session's transcript file, one JSON record per line.
type Transcript string

func (t Transcript) Records(each func(Record) bool) {
	file, err := os.Open(string(t))
	if err != nil {
		return
	}

	defer file.Close()

	reader := bufio.NewReader(file)

	for {
		line, err := reader.ReadString('\n')

		if record, decoded := Decode(line); decoded && !each(record) {
			return
		}

		if err != nil {
			return
		}
	}
}

func (t Transcript) Name(within int) string {
	spoken, name, read := "", "", 0

	t.Records(func(record Record) bool {
		if title := record.Text("aiTitle"); title != "" {
			name = title

			return false
		}

		if spoken == "" && categorise(record) == Prompt {
			spoken = record.Said()
		}

		read++

		return read < within
	})

	if name != "" {
		return name
	}

	return spoken
}

func categorise(record Record) Category {
	kind := record.Text("type")

	switch {
	case slices.Contains(bookkeeping, kind):
		return Bookkeeping
	case kind == "system" && record.Text("subtype") == boundary:
		return Boundary
	case kind == "system" || kind == "attachment":
		return Injected
	case kind == "assistant":
		return Reply
	case kind != "user":
		return Bookkeeping
	case record.flag("isCompactSummary"):
		return Summary
	case record.has("toolUseResult"):
		return ToolResult
	case record.flag("isMeta"):
		return Injected
	case slices.Contains(human, record.Text("promptSource")):
		return Prompt
	default:
		return Injected
	}
}

// Record is one decoded transcript line.
type Record struct {
	fields map[string]any
}

func Decode(line string) (Record, bool) {
	var value any

	if json.Unmarshal([]byte(layout.Trim(line)), &value) != nil {
		return Record{}, false
	}

	switch decoded := value.(type) {
	case map[string]any:
		return Record{decoded}, true
	case []any:
		return Record{map[string]any{}}, true
	default:
		return Record{}, false
	}
}

func (r Record) Text(field string) string {
	return scalar(r.fields[field])
}

func (r Record) Said() string {
	content, found := r.content()
	if !found {
		return ""
	}

	switch content := content.(type) {
	case string:
		return layout.Trim(content)
	case []any:
		var parts []string

		for _, part := range content {
			if part, isMap := part.(map[string]any); isMap && scalar(part["type"]) == "text" {
				parts = append(parts, scalar(part["text"]))
			}
		}

		return layout.Trim(strings.Join(parts, "\n"))
	default:
		return ""
	}
}

func (r Record) content() (any, bool) {
	if message, isMap := r.fields["message"].(map[string]any); isMap && message["content"] != nil {
		return message["content"], true
	}

	if r.fields["content"] != nil {
		return r.fields["content"], true
	}

	return nil, false
}

func (r Record) has(field string) bool {
	return r.fields[field] != nil
}

func (r Record) flag(field string) bool {
	return r.fields[field] == true
}

func scalar(value any) string {
	switch value := value.(type) {
	case string:
		return value
	case bool:
		if value {
			return "1"
		}

		return ""
	case float64:
		return strconv.FormatFloat(value, 'f', -1, 64)
	default:
		return ""
	}
}
