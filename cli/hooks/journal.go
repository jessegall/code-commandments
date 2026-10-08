package hooks

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// settledAs is the word a settled sin's chat mark turns green with.
const settledAs = "fixed"

// Moment is one moment the agent journal hands its plugin: which hook fired, for which session and tool.
type Moment struct {
	Event, Cwd                   string
	Session, Tool, Command, File *string
	Env                          *string
	// Queue is the file the journal runs the moment's environment's lines from, as the payload's plugin.queue names
	// it; empty from a journal that names none.
	Queue string
	// Received is when the hook was handed the moment, in unix nanoseconds; zero when it was not stamped.
	Received int64
}

// MomentOf reads the journal's payload.
func MomentOf(given map[string]any) Moment {
	tool, _ := given["tool"].(map[string]any)
	data, _ := given["data"].(map[string]any)
	agent, _ := given["agent"].(map[string]any)
	plugin, _ := given["plugin"].(map[string]any)
	event := asText(given["event"])

	cwd := firstOf(agent["cwd"], given["project"])
	if cwd == nil {
		here, _ := os.Getwd()
		cwd = here
	}

	return Moment{
		Event:    strings.TrimPrefix(event, "hook."),
		Cwd:      asText(cwd),
		Session:  textOrNil(agent["session"]),
		Tool:     textOrNil(firstOf(tool["name"], data["tool"])),
		Command:  textOrNil(firstOf(tool["command"], data["command"])),
		File:     textOrNil(firstOf(tool["file"], data["file"])),
		Env:      textOrNil(given["env"]),
		Queue:    asText(plugin["queue"]),
		Received: stampOf(given),
	}
}

// IsPostToolUse says whether the moment follows a tool call.
func (m Moment) IsPostToolUse() bool {
	return m.Event == "PostToolUse"
}

// HookPayload is the moment as Claude Code would have reported it.
func (m Moment) HookPayload() map[string]any {
	input := map[string]any{}
	if m.Command != nil {
		input["command"] = *m.Command
	}

	if m.File != nil {
		input["file_path"] = *m.File
	}

	payload := map[string]any{"hook_event_name": m.Event, "cwd": m.Cwd, "tool_input": input}

	if m.Session != nil {
		payload["session_id"] = *m.Session
	}

	if m.Tool != nil {
		payload["tool_name"] = *m.Tool
	}

	if m.Received != 0 {
		payload[receivedKey] = strconv.FormatInt(m.Received, 10)
	}

	return payload
}

// Raise is an event the plugin raises on the journal's bus, with its brief, the dashboard page it opens and the
// key a later settlement names its chat mark by.
type Raise struct {
	Event, Brief, Open, Key string
}

func (r Raise) object() *jsonfile.Object {
	raised := jsonfile.NewObject("event", r.Event, "brief", r.Brief)
	if r.Open != "" {
		raised.Set("open", r.Open)
	}

	if r.Key != "" {
		raised.Set("key", r.Key)
	}

	return raised
}

// JournalAnswer is what the plugin answers the journal for one moment: a reason to refuse the call, a
// whisper for the agent, the events it raises, and the keys of the chat marks it settles as fixed. The journal
// reads a settlement only from the queue, so the answer it waits on carries none.
type JournalAnswer struct {
	Refuse, Whisper *string
	Raises          []Raise
	Settles         []string
}

// JSON is the answer as the journal reads it, leaving out what it does not say.
func (a JournalAnswer) JSON() string {
	answer := jsonfile.NewObject()

	if a.Refuse != nil {
		answer.Set("refuse", *a.Refuse)
	}

	if a.Whisper != nil {
		answer.Set("whisper", *a.Whisper)
	}

	if len(a.Raises) > 0 {
		var raised []any
		for _, raise := range a.Raises {
			raised = append(raised, raise.object())
		}

		answer.Set("raise", raised)
	}

	text, _ := jsonfile.Compact(answer, true)

	return text
}

// said is what the answer tells the agent, as a nudge's title and brief.
func (a JournalAnswer) said() []string {
	switch {
	case a.Refuse != nil:
		return []string{*a.Refuse}
	case a.Whisper != nil:
		return []string{*a.Whisper}
	default:
		return nil
	}
}

// answerOf is the merged response in the journal's shape: a refusal is also whispered, blank context is
// nothing.
func answerOf(merged Response) JournalAnswer {
	if merged.blockReason != nil {
		reason := *merged.blockReason

		return JournalAnswer{Refuse: &reason, Whisper: &reason}
	}

	if merged.context != nil && strings.TrimSpace(*merged.context) != "" {
		whisper := strings.TrimSpace(*merged.context)

		return JournalAnswer{Whisper: &whisper}
	}

	return JournalAnswer{}
}

// Queue is the file the journal hands its plugin for commands it runs later, one per line: how advice worked
// out after the answer reaches the agent.
type Queue struct {
	path string
}

// QueueFromEnvironment is the queue the journal names in $JOURNAL_QUEUE, and whether it names one.
func QueueFromEnvironment() (Queue, bool) {
	path := os.Getenv("JOURNAL_QUEUE")

	return Queue{path}, path != ""
}

// For is the queue of the moment's environment: the journal runs each line in the environment its file belongs to
// and refuses a line that names one, so a moment of an environment is queued in that environment's own file. The
// payload names that file as plugin.queue; a journal that names none had it beside the queue it named. A moment of
// no environment, or of a name no file can carry, keeps the queue as named.
func (q Queue) For(moment Moment) Queue {
	if moment.Queue != "" {
		return Queue{moment.Queue}
	}
	if moment.Env == nil || *moment.Env == "" || filepath.Base(*moment.Env) != *moment.Env || *moment.Env == ".." {
		return q
	}

	return Queue{filepath.Join(filepath.Dir(q.path), workspace.JournalPlugin+"."+*moment.Env+".queue")}
}

// Tell appends to the moment's queue a nudge for what the advice says and a raise for each event it raises, each a
// bare journal command the journal runs as the plugin.
func (q Queue) Tell(advice JournalAnswer, moment Moment) error {
	var commands []string

	for _, said := range advice.said() {
		commands = append(commands, "nudge create "+shellQuote(title(said))+" --brief "+shellQuote(oneLine(said)))
	}

	for _, raise := range advice.Raises {
		command := "plugin raise " + workspace.JournalPlugin + " " + shellQuote(raise.Event) + " " + shellQuote(oneLine(raise.Brief))
		if raise.Open != "" {
			command += " --open " + shellQuote(raise.Open)
		}

		if raise.Key != "" {
			command += " --key " + shellQuote(raise.Key)
		}

		commands = append(commands, command)
	}

	for _, key := range advice.Settles {
		commands = append(commands, "plugin settle "+workspace.JournalPlugin+" "+shellQuote(key)+" --how "+shellQuote(settledAs))
	}

	if len(commands) == 0 {
		return nil
	}

	file, err := os.OpenFile(q.For(moment).path, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o666)
	if err != nil {
		return err
	}

	defer file.Close()

	var text strings.Builder
	for _, command := range commands {
		text.WriteString(command + "\n")
	}

	_, err = file.WriteString(text.String())

	return err
}

// title is the first line of what was said, at most eighty characters, a colon turned into a dash.
func title(text string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(text, "\n"), "\n")
	first = strings.TrimSpace(strings.ReplaceAll(first, ":", " —"))

	if utf8.RuneCountInString(first) <= 80 {
		return first
	}

	return strings.TrimRight(string([]rune(first)[:79]), " \t\n\r\x00\x0B") + "…"
}

// oneLine is the text's non-blank lines, trimmed, on one line.
func oneLine(text string) string {
	var lines []string

	for _, line := range strings.Split(text, "\n") {
		if trimmed := strings.TrimSpace(line); trimmed != "" {
			lines = append(lines, trimmed)
		}
	}

	return strings.Join(lines, " · ")
}

// shellQuote is the text as one shell word, as PHP's escapeshellarg writes it.
func shellQuote(text string) string {
	return "'" + strings.ReplaceAll(text, "'", `'\''`) + "'"
}

// Announced are the sins the plugin has announced, per file and by each sin's identity, so a sin is found
// once and repented once.
type Announced struct {
	files *jsonfile.Object
	path  string
}

// AnnouncedIn is the record kept in the plugin's data folder; an older record that listed sins without
// their identity proves nothing and starts over.
func AnnouncedIn(folder string) Announced {
	path := filepath.Join(folder, "sins.json")
	files := jsonfile.NewObject()

	if read, ok := jsonfile.Read(path); ok {
		for _, file := range read.Keys() {
			value, _ := read.Get(file)

			if sins, isObject := value.(*jsonfile.Object); isObject {
				files.Set(file, sins)
			}
		}
	}

	return Announced{files, path}
}

// Forgotten is a record that keeps nothing: every touched sin is new.
func Forgotten() Announced {
	return Announced{jsonfile.NewObject(), ""}
}

// Settle records what the moment changed: the touched sins not announced before are found, and a sin
// announced for the edited file that the file no longer holds is repented, as is every sin announced for a file
// that is gone, deleted by a shell command the plugin sees no file of.
func (a Announced) Settle(root string, edited *string, marks []SinMark) Settlement {
	var files []string
	now := map[string][]SinMark{}

	for _, mark := range marks {
		file := source.Relative(root, mark.Match.File())
		if _, seen := now[file]; !seen {
			files = append(files, file)
		}

		now[file] = append(now[file], mark)
	}

	judged := ""
	if edited != nil {
		judged = source.Relative(root, *edited)

		if _, seen := now[judged]; !seen {
			files = append(files, judged)
		}
	}

	gone := map[string]bool{}
	for _, file := range a.files.Keys() {
		if _, seen := now[file]; seen || file == judged {
			continue
		}

		if _, err := os.Stat(filepath.Join(root, file)); os.IsNotExist(err) {
			gone[file] = true
			files = append(files, file)
		}
	}

	var found []SinMark
	var resolvedIDs []string
	resolved := map[string]string{}

	for _, file := range files {
		before := jsonfile.NewObject()
		if value, has := a.files.Get(file); has {
			before = value.(*jsonfile.Object)
		}

		holds := map[string]SinMark{}
		var holdOrder []string

		for _, mark := range now[file] {
			if _, seen := holds[mark.ID()]; !seen {
				holdOrder = append(holdOrder, mark.ID())
			}

			holds[mark.ID()] = mark
		}

		kept := jsonfile.NewObject()

		for _, id := range before.Keys() {
			if _, holding := holds[id]; file != judged && !gone[file] || holding {
				value, _ := before.Get(id)
				kept.Set(id, value)
			}
		}

		for _, id := range before.Keys() {
			if _, stays := kept.Get(id); stays {
				continue
			}

			if _, listed := resolved[id]; !listed {
				value, _ := before.Get(id)
				resolved[id] = asText(value)
				resolvedIDs = append(resolvedIDs, id)
			}
		}

		for _, id := range holdOrder {
			mark := holds[id]

			if _, announced := before.Get(id); mark.Touched && !announced {
				found = append(found, mark)

				if _, has := kept.Get(id); !has {
					kept.Set(id, mark.ShownFrom(root))
				}
			}
		}

		a.files.Set(file, kept)
	}

	a.keep()

	var repented []Repented
	for _, id := range resolvedIDs {
		repented = append(repented, Repented{id, resolved[id]})
	}

	return Settlement{found, repented}
}

// keep writes the record, less the files that hold no sin; a record of none is an empty list, as PHP writes it.
func (a Announced) keep() {
	if a.path == "" {
		return
	}

	record := jsonfile.NewObject()
	kept := 0

	for _, file := range a.files.Keys() {
		value, _ := a.files.Get(file)
		sins := value.(*jsonfile.Object)

		if len(sins.Keys()) == 0 {
			continue
		}

		ids := jsonfile.NewObject()
		for _, id := range sins.Keys() {
			shown, _ := sins.Get(id)
			ids.Set(id, asText(shown))
		}

		record.Set(file, ids)
		kept++
	}

	text, err := jsonfile.Compact(record, false)
	if kept == 0 {
		text, err = "[]", nil
	}

	if err == nil {
		os.WriteFile(a.path, []byte(text), 0o666)
	}
}

// Settlement is what one moment changed in the announced sins: the sins found, and the sins repented.
type Settlement struct {
	Found    []SinMark
	Repented []Repented
}

// Raises are the events the settlement raises: a sin found, each opening its page on the dashboard under the key
// its chat mark is settled by, and the sins repented together.
func (s Settlement) Raises(root string) []Raise {
	var raised []Raise

	for _, mark := range s.Found {
		raised = append(raised, Raise{"sin-found", mark.ShownFrom(root), dashboard.Opening(dashboard.StoredOf(mark.Finding(), root)), mark.Key()})
	}

	if len(s.Repented) > 0 {
		var shown []string
		for _, sin := range s.Repented {
			shown = append(shown, sin.Shown)
		}

		raised = append(raised, Raise{Event: "sin-resolved", Brief: strings.Join(shown, "\n")})
	}

	return raised
}

// Settles are the keys of the chat marks the repented sins were found under.
func (s Settlement) Settles() []string {
	var keys []string
	for _, sin := range s.Repented {
		keys = append(keys, markKey(sin.ID))
	}

	return keys
}

// Repented is a sin announced before that the code no longer holds: its identity and how it was shown.
type Repented struct {
	ID, Shown string
}

// markKey is the key a sin's chat mark is raised and settled by: its identity, which an edit above it does not change.
func markKey(id string) string {
	return "sin:" + id
}

func firstOf(values ...any) any {
	for _, value := range values {
		if value != nil {
			return value
		}
	}

	return nil
}

func textOrNil(value any) *string {
	if value == nil {
		return nil
	}

	text := asText(value)

	return &text
}
