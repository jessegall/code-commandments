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

const settledAs = "fixed"

// Moment is one moment the agent journal hands its plugin: which hook fired, for which session and tool.
type Moment struct {
	Event, Cwd                   string
	Session, Tool, Command, File *string
	Env                          *string
	Queue                        string
	Received                     int64
	Changed                      []int
	ChangedWhole                 bool
	Agent                        int
	Batched                      []any
	BatchedLines                 map[string]any
	Cut                          string
}

const changedKey = "commandments_changed"

const cutKey = "commandments_cut"

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
		Event:        strings.TrimPrefix(event, "hook."),
		Cwd:          asText(cwd),
		Session:      textOrNil(agent["session"]),
		Agent:        asAgent(agent["id"]),
		Tool:         textOrNil(firstOf(tool["name"], data["tool"])),
		Command:      textOrNil(firstOf(tool["command"], data["command"])),
		File:         textOrNil(firstOf(tool["file"], data["file"])),
		Env:          textOrNil(given["env"]),
		Queue:        asText(plugin["queue"]),
		Received:     stampOf(given),
		Changed:      changedLinesOf(data),
		ChangedWhole: asText(firstOf(data["type"], data["kind"])) == "create",
		Batched:      batchedFilesIn(given),
		BatchedLines: batchedLinesIn(given),
		Cut:          cutIn(data),
	}
}

func cutIn(data map[string]any) string {
	checkout, named := data["checkout"].(map[string]any)
	if !named {
		return ""
	}

	return asText(firstOf(checkout["base_commit"], checkout["base"]))
}

func batchedFilesIn(given map[string]any) []any {
	held, _ := given[batchedKey].([]any)

	return held
}

func batchedLinesIn(given map[string]any) map[string]any {
	held, _ := given[batchedLinesKey].(map[string]any)

	return held
}

func changedLinesOf(data map[string]any) []int {
	rows, _ := data["lines"].([]any)
	var lines []int
	for _, each := range rows {
		row, _ := each.(map[string]any)
		if asText(firstOf(row["type"], row["kind"], row["line_type"])) != "add" {
			continue
		}
		lines = append(lines, intOf(row["line"]))
	}

	return lines
}

func (m Moment) Environment() (string, bool) {
	if m.Env == nil || *m.Env == "" || filepath.Base(*m.Env) != *m.Env || *m.Env == ".." {
		return "", false
	}

	return *m.Env, true
}

func (m Moment) IsPostToolUse() bool {
	return m.Event == "PostToolUse"
}

func (m Moment) IsStop() bool {
	return m.Event == "Stop"
}

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

	if len(m.Batched) > 0 {
		payload[batchedKey] = m.Batched
		payload[batchedLinesKey] = m.BatchedLines
	}

	if m.Cut != "" {
		payload[cutKey] = m.Cut
	}

	return payload
}

// Raise is an event the plugin raises on the journal's bus, with its brief, the dashboard page it opens, the key a later settlement names its chat mark by, and the detail its card holds — the sins a file's mark lists.
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

// JournalAnswer is what the plugin answers the journal for one moment: a reason to refuse the call, a whisper for the agent, the events it raises, and the keys of the chat marks it settles as fixed.
type JournalAnswer struct {
	Refuse, Whisper *string
	Raises          []Raise
	Settles         []string
	Repented        []Repented
	Requires        []string
	JudgedMs        int64

	ReadMs int64

	JudgedTotalMs int64
}

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

func answerOf(merged Response) JournalAnswer {
	if merged.blockReason != nil {
		reason := *merged.blockReason

		return JournalAnswer{Refuse: &reason, Whisper: &reason, Requires: merged.Requires}
	}

	if merged.context != nil && strings.TrimSpace(*merged.context) != "" {
		whisper := strings.TrimSpace(*merged.context)

		return JournalAnswer{Whisper: &whisper, Requires: merged.Requires}
	}

	return JournalAnswer{Requires: merged.Requires}
}

// Queue is the file the journal hands its plugin for commands it runs later, one per line: how advice worked out after the answer reaches the agent.
type Queue struct {
	path string
}

func QueueFromEnvironment() (Queue, bool) {
	path := os.Getenv("JOURNAL_QUEUE")

	return Queue{path}, path != ""
}

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

func title(text string) string {
	first, _, _ := strings.Cut(strings.TrimLeft(text, "\n"), "\n")
	first = strings.TrimSpace(strings.ReplaceAll(first, ":", " —"))

	if utf8.RuneCountInString(first) <= 80 {
		return first
	}

	return strings.TrimRight(string([]rune(first)[:79]), " \t\n\r\x00\x0B") + "…"
}

func oneLine(text string) string {
	var lines []string

	for _, line := range strings.Split(text, "\n") {
		if trimmed := strings.TrimSpace(line); trimmed != "" {
			lines = append(lines, trimmed)
		}
	}

	return strings.Join(lines, " · ")
}

func shellQuote(text string) string {
	return "'" + strings.ReplaceAll(text, "'", `'\''`) + "'"
}

// Announced are the sins the plugin has announced, per file and by each sin's identity, so a sin is found once and repented once.
type Announced struct {
	files *jsonfile.Object
	path  string
}

func AnnouncedIn(folder string, moment Moment) Announced {
	path := filepath.Join(folder, "sins"+namesTheAgent(moment)+".json")
	if env, named := moment.Environment(); named {
		path = filepath.Join(folder, "sins."+env+namesTheAgent(moment)+".json")
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

func namesTheAgent(moment Moment) string {
	if moment.Agent == 0 {
		return ""
	}

	return "." + strconv.Itoa(moment.Agent)
}

func (a Announced) Files() []string {
	return a.files.Keys()
}

func Forgotten() Announced {
	return Announced{jsonfile.NewObject(), ""}
}

// Asked is the sins a judging put to each file it judged, by that file's path from the root. A judging that asked
// every sin the project enabled names none, and an empty Asked means every file was asked everything.
//
// It is what keeps a partial judging from calling a file clean. A judging that could only ask the rules which
// answer from one file, which is what happens after a restart and during every plugin upgrade, used to read every
// announced sin it had not looked for as gone: it told the agent the file was FIXED, turned its card green, and
// announced the same sins again as new once a whole judging returned.
type Asked map[string][]string

func (a Asked) asked(file string, sin string) bool {
	names, narrowed := a[file]
	if !narrowed {
		return true
	}

	return sin != "" && slices.Contains(names, sin)
}

func (a Announced) Settle(root string, read []string, marks []SinMark) Settlement {
	return a.SettleAsked(root, read, marks, nil)
}

func (a Announced) SettleAsked(root string, read []string, marks []SinMark, asked Asked) Settlement {
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
			_, holding := holds[id]
			stays := !judged[file] && !gone[file] || holding

			if !stays && !gone[file] && !asked.asked(file, announcedIn(before, id).Sin) {
				stays = true
			}

			if stays {
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

		for _, id := range holdOrder {
			mark := holds[id]

			if _, announced := before.Get(id); !mark.Touched || announced {
				continue
			}

			found = append(found, mark)

			if _, has := kept.Get(id); !has {
				kept.Set(id, announcement{mark.ShownFrom(root), mark.Key(root), mark.Rule.Sin().Definition().Name}.written(id))
			}
		}

		for _, id := range leaving {
			if _, listed := resolved[id]; !listed {
				resolved[id] = announcedIn(before, id)
				resolvedIDs = append(resolvedIDs, id)
			}
		}

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

func holdsAnyOf(found []SinMark, file, root string) bool {
	return slices.ContainsFunc(found, func(mark SinMark) bool { return source.Relative(root, mark.Match.File()) == file })
}

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
	Files    []FileSins
}

// FileSins is one file and every sin it holds, as the chat's mark for that file lists them.
type FileSins struct {
	Path string
	Sins []SinMark
}

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

func (f FileSins) counted() string {
	if len(f.Sins) == 1 {
		return "1 sin in " + f.Path
	}

	return strconv.Itoa(len(f.Sins)) + " sins in " + f.Path
}

func (s Settlement) Settles() []string {
	var keys []string
	for _, file := range s.Files {
		if len(file.Sins) == 0 {
			keys = append(keys, fileKey(file.Path))
		}
	}

	return keys
}

// Repented is a sin announced before that the code no longer holds: the key its chat mark was raised under, and how it was shown.
type Repented struct {
	Key, Shown string
}

// announcement is a sin the record holds: how it was shown, the key its chat mark was raised under, and the sin it
// is, which is what says whether a later judging even asked about it.
type announcement struct {
	Shown, Key, Sin string
}

func announcedIn(record *jsonfile.Object, id string) announcement {
	value, _ := record.Get(id)
	if entry, carried := value.(*jsonfile.Object); carried {
		shown, _ := entry.Get("shown")
		key, _ := entry.Get("key")
		sin, _ := entry.Get("sin")

		return announcement{asText(shown), asText(key), asText(sin)}
	}

	return announcement{asText(value), markKey(id), ""}
}

func (a announcement) written(id string) any {
	if a.Key == markKey(id) && a.Sin == "" {
		return a.Shown
	}
	entry := jsonfile.NewObject()
	entry.Set("shown", a.Shown)
	entry.Set("key", a.Key)
	if a.Sin != "" {
		entry.Set("sin", a.Sin)
	}

	return entry
}

func markKey(id string) string {
	return "sin:" + id
}

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
