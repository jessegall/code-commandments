package hooks

import (
	"cmp"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
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
	// Changed are the lines of the moment's file its edit wrote, when whoever reported the moment had already worked
	// them out; none when nobody did, and then they are asked of git. Whole says every line is the edit's, which is
	// what a file just created is.
	Changed      []int
	ChangedWhole bool
}

// changedKey is where a payload carries the lines an edit wrote, so the handlers read them instead of asking git
// for a diff the reporter already had.
const changedKey = "commandments_changed"

// ChangedIn are the lines an edit wrote as the payload carries them, and whether they are there at all.
func ChangedIn(payload map[string]any) (git.ChangedLines, bool) {
	said, carried := payload[changedKey].(map[string]any)
	if !carried {
		return git.ChangedLines{}, false
	}

	if whole, is := said["whole"].(bool); is && whole {
		return git.Everywhere(), true
	}

	written, _ := said["lines"].([]any)
	var lines []int
	for _, line := range written {
		lines = append(lines, intOf(line))
	}

	return git.AtLines(lines), true
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
		Changed:  changedLinesOf(data),
		// A file the tool created is new to its last line, so every line of it is the edit's.
		ChangedWhole: asText(data["kind"]) == "create",
	}
}

// changedLinesOf are the line numbers of the rows an edit added, as the journal's own edit row records them. A
// removed row is not a line of the file any more, so only what was added can hold a sin.
func changedLinesOf(data map[string]any) []int {
	rows, _ := data["lines"].([]any)
	var lines []int
	for _, each := range rows {
		row, _ := each.(map[string]any)
		if asText(row["kind"]) != "add" {
			continue
		}
		lines = append(lines, intOf(row["line"]))
	}

	return lines
}

// Environment is the name of the environment the moment happened in, when it names one a file name can carry.
func (m Moment) Environment() (string, bool) {
	if m.Env == nil || *m.Env == "" || filepath.Base(*m.Env) != *m.Env || *m.Env == ".." {
		return "", false
	}

	return *m.Env, true
}

// IsPostToolUse says whether the moment follows a tool call.
func (m Moment) IsPostToolUse() bool {
	return m.Event == "PostToolUse"
}

// IsStop says whether the moment is the agent going idle: the end of its turn, when the files that still carry a
// mark are judged again.
func (m Moment) IsStop() bool {
	return m.Event == "Stop"
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

	if m.ChangedWhole {
		payload[changedKey] = map[string]any{"whole": true}
	} else if len(m.Changed) > 0 {
		written := make([]any, 0, len(m.Changed))
		for _, line := range m.Changed {
			written = append(written, line)
		}
		payload[changedKey] = map[string]any{"lines": written}
	}

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

// Raise is an event the plugin raises on the journal's bus, with its brief, the dashboard page it opens, the key a
// later settlement names its chat mark by, and the detail its card holds — the sins a file's mark lists.
type Raise struct {
	Event, Brief, Open, Key string
	Detail                  string
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
	// Repented are the sins Settles names, each with how it was shown, for version 4 of the journal, which settles a
	// card in place and says which sin it was.
	Repented []Repented
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

	env, named := moment.Environment()
	if !named {
		return q
	}

	return Queue{filepath.Join(filepath.Dir(q.path), workspace.JournalPlugin+"."+env+".queue")}
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

// AnnouncedIn is the record kept in the plugin's data folder for the moment's environment, whose chat shows the
// sins it announced and whose queue settles them, so a moment in another environment's checkout never repents them;
// an older record that listed sins without their identity proves nothing and starts over.
func AnnouncedIn(folder string, moment Moment) Announced {
	path := filepath.Join(folder, "sins.json")
	if env, named := moment.Environment(); named {
		path = filepath.Join(folder, "sins."+env+".json")
	}
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

// Files are the files the record holds sins for.
func (a Announced) Files() []string {
	return a.files.Keys()
}

// Forgotten is a record that keeps nothing: every touched sin is new.
func Forgotten() Announced {
	return Announced{jsonfile.NewObject(), ""}
}

// Settle records what the moment changed: the touched sins not announced before are found, and a sin
// announced for the edited file that the file no longer holds is repented, as is every sin announced for a file
// that is gone, deleted by a shell command the plugin sees no file of.
func (a Announced) Settle(root string, read []string, marks []SinMark) Settlement {
	var files []string
	now := map[string][]SinMark{}

	for _, mark := range marks {
		file := source.Relative(root, mark.Match.File())
		if _, seen := now[file]; !seen {
			files = append(files, file)
		}

		now[file] = append(now[file], mark)
	}

	judged := map[string]bool{}
	for _, each := range read {
		file := source.Relative(root, each)
		judged[file] = true

		if _, seen := now[file]; !seen && !slices.Contains(files, file) {
			files = append(files, file)
		}
	}

	gone := map[string]bool{}
	for _, file := range a.files.Keys() {
		if _, seen := now[file]; seen || judged[file] {
			continue
		}

		if _, err := os.Stat(filepath.Join(root, file)); os.IsNotExist(err) {
			gone[file] = true
			files = append(files, file)
		}
	}

	var found []SinMark
	var resolvedIDs []string
	var changed []FileSins
	resolved := map[string]announcement{}

	for _, file := range files {
		before := jsonfile.NewObject()
		if value, has := a.files.Get(file); has {
			before = value.(*jsonfile.Object)
		}

		holds := map[string]SinMark{}
		var holdOrder []string

		for _, mark := range now[file] {
			if _, seen := holds[mark.ID(root)]; !seen {
				holdOrder = append(holdOrder, mark.ID(root))
			}

			holds[mark.ID(root)] = mark
		}

		kept := jsonfile.NewObject()

		for _, id := range before.Keys() {
			if _, holding := holds[id]; !judged[file] && !gone[file] || holding {
				value, _ := before.Get(id)
				kept.Set(id, value)
			}
		}

		var leaving []string
		for _, id := range before.Keys() {
			if _, stays := kept.Get(id); !stays {
				leaving = append(leaving, id)
			}
		}

		// Every sin remembered for a file this moment judged and which the judge no longer finds is repented,
		// however it went: its line edited, rewritten, moved or gone. The comparison is the remembered keys less
		// the keys found now, over the WHOLE file and never only where the edit landed — a sin two hundred lines
		// above an edit is as fixed as one on its own line, and a card nothing settles stays orange for good.
		for _, id := range holdOrder {
			mark := holds[id]

			if _, announced := before.Get(id); !mark.Touched || announced {
				continue
			}

			found = append(found, mark)

			if _, has := kept.Get(id); !has {
				kept.Set(id, mark.ShownFrom(root))
			}
		}

		for _, id := range leaving {
			if _, listed := resolved[id]; !listed {
				resolved[id] = announcedIn(before, id)
				resolvedIDs = append(resolvedIDs, id)
			}
		}

		// The chat gets ONE mark per file, with the sins it holds now listed in it, so thirty sins in a file are
		// one card that is rewritten rather than thirty under each other. Nothing changed in a file nobody judged.
		if len(leaving) > 0 || len(found) > 0 && holdsAnyOf(found, file, root) {
			changed = append(changed, FileSins{file, inFile(now[file], holdOrder, holds)})
		}

		a.files.Set(file, kept)
	}

	a.keep()

	var repented []Repented
	for _, id := range resolvedIDs {
		repented = append(repented, Repented{resolved[id].Key, resolved[id].Shown})
	}

	return Settlement{found, repented, changed}
}

// holdsAnyOf says whether any of the marks found is in the file.
func holdsAnyOf(found []SinMark, file, root string) bool {
	return slices.ContainsFunc(found, func(mark SinMark) bool { return source.Relative(root, mark.Match.File()) == file })
}

// inFile are the sins the file holds now, in the order they were read, one per identity.
func inFile(marks []SinMark, order []string, holds map[string]SinMark) []SinMark {
	if len(marks) == 0 {
		return nil
	}

	kept := make([]SinMark, 0, len(order))
	for _, id := range order {
		kept = append(kept, holds[id])
	}

	return kept
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
			entry, _ := sins.Get(id)
			ids.Set(id, entry)
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
	// Files are the judged files whose sins changed, each with every sin it holds now: the chat's one mark per
	// file, which a later judging rewrites in place. A file with none left is one whose last sin is repented.
	Files []FileSins
}

// FileSins is one file and every sin it holds, as the chat's mark for that file lists them.
type FileSins struct {
	Path string
	Sins []SinMark
}

// Raises are the events the settlement raises: ONE per file whose sins changed, keyed by the file so a later
// judging rewrites it where it stands, listing every sin the file holds now and opening the file's page on the
// dashboard. A file left clean is raised as repented under that same key, which turns its mark green.
func (s Settlement) Raises(root string) []Raise {
	var raised []Raise

	for _, file := range s.Files {
		if len(file.Sins) == 0 {
			raised = append(raised, Raise{Event: "sin-resolved", Brief: file.Path + " is clean", Key: fileKey(file.Path)})

			continue
		}

		raised = append(raised, Raise{
			Event:  "sin-found",
			Brief:  file.counted(),
			Open:   dashboard.Opening(dashboard.StoredOf(file.Sins[0].Finding(), root)),
			Key:    fileKey(file.Path),
			Detail: strings.Join(file.named(), "\n"),
		})
	}

	return raised
}

// named are the lines under a file's mark: which sins they are and where. The file is left out, since the mark's
// first line already names it, so each line is a sin and the lines it sits on, one per sin with the largest group
// first. A mark opens only where it has two lines or more, so a file holding one sin alone is given its rule's
// own words underneath rather than left as a line nobody can open.
func (f FileSins) named() []string {
	var order []string
	at := map[string][]int{}

	for _, sin := range f.Sins {
		name := sin.Rule.Sin().Definition().Name
		if _, seen := at[name]; !seen {
			order = append(order, name)
		}

		at[name] = append(at[name], sin.Match.Line())
	}

	slices.SortStableFunc(order, func(a, b string) int { return cmp.Compare(len(at[b]), len(at[a])) })

	var named []string
	for _, name := range order {
		lines := slices.Compact(slices.Sorted(slices.Values(at[name])))
		var numbers []string
		for _, line := range lines {
			numbers = append(numbers, strconv.Itoa(line))
		}

		word := " at line "
		if len(numbers) > 1 {
			word = " at lines "
		}

		named = append(named, name+word+strings.Join(numbers, ", "))
	}

	if len(named) == 1 {
		if says := f.Sins[0].Rule.Sin().Definition().Description; says != "" {
			named = append(named, says)
		}
	}

	return named
}

// counted is how a file's mark names itself: the file, and how many sins it holds.
func (f FileSins) counted() string {
	if len(f.Sins) == 1 {
		return "1 sin in " + f.Path
	}

	return strconv.Itoa(len(f.Sins)) + " sins in " + f.Path
}

// Settles are the keys of the chat marks that are settled: a file's mark, once the file holds no sin.
func (s Settlement) Settles() []string {
	var keys []string
	for _, file := range s.Files {
		if len(file.Sins) == 0 {
			keys = append(keys, fileKey(file.Path))
		}
	}

	return keys
}

// Repented is a sin announced before that the code no longer holds: the key its chat mark was raised under, and how
// it was shown.
type Repented struct {
	Key, Shown string
}

// announcement is a sin the record holds: how it was shown, and the key its chat mark was raised under. A sin whose
// own line was edited while it stayed is the same sin under a new identity, and keeps the mark it was first raised
// under.
type announcement struct {
	Shown, Key string
}

// announcedIn is the sin the file's record holds under the identity: its shown text alone, raised under its own
// identity's key, or the shown text beside the key it kept through an edit.
func announcedIn(record *jsonfile.Object, id string) announcement {
	value, _ := record.Get(id)
	if entry, carried := value.(*jsonfile.Object); carried {
		shown, _ := entry.Get("shown")
		key, _ := entry.Get("key")

		return announcement{asText(shown), asText(key)}
	}

	return announcement{asText(value), markKey(id)}
}

// written is the sin as the record holds it under the identity: its shown text alone while its key is that
// identity's own.
func (a announcement) written(id string) any {
	if a.Key == markKey(id) {
		return a.Shown
	}
	entry := jsonfile.NewObject()
	entry.Set("shown", a.Shown)
	entry.Set("key", a.Key)

	return entry
}

// markKey is the key a sin's chat mark is raised and settled by: its identity, which an edit above it does not change.
func markKey(id string) string {
	return "sin:" + id
}

// fileKey is the key a file's own chat mark is raised and settled under, so every judging of that file rewrites
// the one mark rather than adding another beside it.
func fileKey(path string) string {
	return "sins:" + path
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
