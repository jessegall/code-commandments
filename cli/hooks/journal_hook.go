package hooks

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/jessegall/code-commandments/cli/binary"
	"io"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
)

const (
	quietHooks   = "COMMANDMENTS_QUIET_HOOKS"
	pluginData   = "JOURNAL_PLUGIN_DATA"
	advising     = "COMMANDMENTS_ADVISING"
	pluginSocket = "JOURNAL_PLUGIN_SOCKET"
	journalRoot  = "JOURNAL_ROOT"
	hooksService = "hooks"
)

// JournalHook is `journal-hook`: the entry point the agent journal's plugin calls for every moment.
type JournalHook struct{}

func (JournalHook) Names() []string {
	return []string{"journal-hook"}
}

func (JournalHook) Help() help.Help {
	return help.Of("The agent journal's entry point — reads one journal hook payload from stdin, runs every registered handler, and answers in the journal's shape.").
		Form("journal-hook", "answer the moment on stdin (wired by the journal plugin; you rarely run this by hand)").
		In(help.Hooks)
}

func (JournalHook) Run(in *cli.Input, console cli.Console) (int, error) {
	raw, _ := io.ReadAll(os.Stdin)
	given := payloadOf(raw)

	if os.Getenv(advising) != "" {
		adviseNow(given)

		return 0, nil
	}

	given = Stamped(given)
	console.Write(AnswerNow(given).JSON() + "\n")

	if _, queued := QueueFromEnvironment(); queued {
		stamped, _ := json.Marshal(given)
		adviseDetached(stamped)
	}

	return 0, nil
}

func AnswerNow(given map[string]any) JournalAnswer {
	if _, queued := QueueFromEnvironment(); queued {
		return answerWith(given, isGate, false)
	}

	return answerWith(given, every, true)
}

func answer4On(given map[string]any, event string) string {
	if judgedAfter(event) {
		return answerWith(given, isGate, false).Line4()
	}

	return answerWith(given, every, true).Line4()
}

func adviseNow(given map[string]any) {
	if queue, queued := QueueFromEnvironment(); queued {
		queue.Tell(answerWith(given, not(isGate), true), MomentOf(given))
	}
}

func adviseAsked(moment asked) {
	if !moment.queued {
		adviseNow(moment.given)

		return
	}

	queue, queued := Queue4In(os.Getenv(pluginData))
	if !queued {
		return
	}

	if why := abandoned(moment.given); why != "" {
		abandonedJudgings.Add(1)
		held := MomentOf(moment.given)

		if recorded(held.Cwd) {
			Record(bookkeepingIn(held.Cwd), Judged{
				At: time.Now(), Event: held.Event, Kind: "checkout", Checkout: held.Cwd, Abandoned: why,
			})
		}

		return
	}

	answer := answerWith(moment.given, not(isGate), true)

	if why := abandoned(moment.given); why != "" {
		abandonedJudgings.Add(1)

		return
	}

	_ = queue.Tell(answer, asText(moment.given["env"]), MomentOf(moment.given).Agent)
}

var abandonedJudgings atomic.Int64

func AbandonedJudgings() int64 { return abandonedJudgings.Load() }

func abandoned(given map[string]any) string {
	held := MomentOf(given)
	if held.File == nil {
		return ""
	}

	checkout := checkoutOf(workspace.ProjectRoot(held.Cwd), *held.File)
	if gone(checkout) {
		return string(checkoutGone)
	}

	if held.Cut == "" {
		return ""
	}

	if cut := kept.CutOf(checkout); cut != "" && cut != held.Cut {
		return "this checkout was cut again, from " + cut
	}

	return ""
}

func adviseDetached(raw []byte) {
	self, err := os.Executable()
	if err != nil {
		return
	}

	moment, err := momentFile(raw)
	if err != nil {
		return
	}
	defer moment.Close()

	run := exec.Command(self, "journal-hook")
	run.Stdin = moment
	run.Env = append(os.Environ(), advising+"=1")
	detach(run)

	if run.Start() == nil {
		run.Process.Release()
	}
}

func momentFile(raw []byte) (*os.File, error) {
	file, err := os.CreateTemp("", "commandments-moment-*")
	if err != nil {
		return nil, err
	}
	defer os.Remove(file.Name())

	if _, err := file.Write(raw); err != nil {
		file.Close()

		return nil, err
	}

	if _, err := file.Seek(0, io.SeekStart); err != nil {
		file.Close()

		return nil, err
	}

	return file, nil
}

func every(Hook) bool { return true }

func isGate(hook Hook) bool {
	_, gate := hook.(Gate)

	return gate
}

func not(keep func(Hook) bool) func(Hook) bool {
	return func(hook Hook) bool { return !keep(hook) }
}

func answerWith(given map[string]any, runs func(Hook) bool, reportsSins bool) JournalAnswer {
	moment := MomentOf(given)
	if moment.Event == "" {
		return JournalAnswer{}
	}

	cwd := moment.Cwd
	if info, err := os.Stat(cwd); err != nil || !info.IsDir() {
		cwd, _ = os.Getwd()
	}

	if why := notForThisRole(moment); why != "" {
		return JournalAnswer{Whisper: &why}
	}

	event := NewEvent(moment.HookPayload(), workspace.ProjectRoot(cwd))
	workspace.At(event.Root, "").RelocateSessions()
	quiet := quieted()

	var responses []Response

	judging := time.Now()

	for _, hook := range ForProject(event.Root) {
		if runs(hook) && !slices.Contains(quiet, hook.Class()) {
			responses = append(responses, Answer(hook, event))
		}
	}

	spent := time.Since(judging)

	MemoryNow()

	merged := Merge(responses)
	answer := answerOf(merged)
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	if !reportsSins || !moment.IsPostToolUse() && !moment.IsStop() {
		return answer
	}

	recordJudging(moment, event, merged, answer, spent)

	settlement := settled(moment, event.Root, merged.Activity, merged.Judged, merged.Asked)
	answer.Raises = append(answer.Raises, settlement.Raises(event.Root)...)
	answer.Settles = settlement.Settles()
	answer.Repented = settlement.Repented
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	return answer
}

func recordJudging(moment Moment, event Event, merged Response, answer JournalAnswer, spent time.Duration) {
	folder := bookkeepingIn(event.Root)
	if folder == "" || len(merged.Judged) == 0 || !recorded(event.Root) {
		return
	}

	checkout := checkoutOf(event.Root, moment.Cwd)
	kind := "project"
	if checkout != strings.TrimRight(git.ProjectRoot(event.Root), "/") {
		kind = "worktree"
	}

	named, _ := rootOf(event.Root)
	_, _, held := baseFor(event.Root)

	why := Narrowed("")
	asked, notAsked := 0, 0
	for file, sins := range merged.Asked {
		asked = len(sins)
		notAsked = len(merged.Narrowed)
		why = merged.Narrowed[file]
	}

	Record(folder, Judged{
		At: time.Now(), Event: moment.Event, Checkout: checkout, Kind: kind, Branch: named.branch,
		Held: held, Why: why, Overlay: kept.Over(checkout).Count(), Parsed: len(merged.Judged),
		ReadMs: merged.ReadMs, RuleMs: milliseconds(spent) - merged.ReadMs, Asked: asked, NotAsked: notAsked,
		Sins: len(merged.Activity), Dropped: DroppedSoFar(), Memory: MemoryNow(), Said: moment.CheckoutSaid, Files: merged.Judged,
	})
}

func ceilingChosen() int {
	chosen, given := chosenSettings()
	if !given {
		return 0
	}

	said, named := chosen[ceilingKey]
	if !named {
		return 0
	}

	nodes, err := strconv.Atoi(strings.TrimSpace(asText(said)))
	if err != nil || nodes < 0 {
		return 0
	}

	return nodes
}

func notForThisRole(moment Moment) string {
	if moment.Role == "" || !workerRoles[moment.Role] || workersJudgedAt(moment) {
		return ""
	}

	return Unjudged("a "+moment.Role+"'s own edits are not judged",
		"the setting "+workersKey+" is off, and its work is judged when it is taken in",
		binary.JudgingChanges(moment.Cwd))
}

func filesInOneGoChosen() int {
	chosen, given := chosenSettings()
	if !given {
		return 0
	}

	said, named := chosen[filesInOneGoKey]
	if !named {
		return 0
	}

	files, err := strconv.Atoi(strings.TrimSpace(asText(said)))
	if err != nil || files < 1 {
		return 0
	}

	return files
}

func workersJudged() bool {
	chosen, given := chosenSettings()
	if !given {
		return false
	}

	said, named := chosen[workersKey]

	return named && switchedOn(said)
}

func workersJudgedAt(moment Moment) bool {
	if said, named := moment.Settings[workersKey]; named {
		return switchedOn(said)
	}

	return workersJudged()
}

func switchedOn(value any) bool {
	if on, isFlag := value.(bool); isFlag {
		return on
	}

	return asText(value) == "true"
}

func settingsOn(given map[string]any) map[string]any {
	settings, _ := given["settings"].(map[string]any)

	return settings
}

func recorded(string) bool {
	chosen, given := chosenSettings()
	if !given {
		return true
	}

	said, named := chosen[recordKey]

	return !named || asText(said) != "false"
}

func settled(moment Moment, root string, marks []SinMark, read []string, asked Asked) Settlement {
	data := os.Getenv(pluginData)
	reconsidered := append(read, named(moment.File)...)

	if data == "" {
		return Forgotten().SettleAsked(root, reconsidered, marks, asked)
	}

	record := AnnouncedIn(data, moment)

	if moment.IsStop() {
		swept, files := sweptMarks(record, root)
		marks = append(marks, swept...)
		reconsidered = append(reconsidered, files...)
	}

	var findings []engine.Finding
	judged := map[string]bool{}

	if moment.File != nil && *moment.File != "" {
		judged[resolved(moment.Cwd, *moment.File)] = true
	}

	for _, mark := range marks {
		findings = append(findings, mark.Finding())
		judged[resolved(moment.Cwd, mark.Match.File())] = true
	}

	dashboard.Record(workspace.At(root, ""), findings, judged)

	return record.SettleAsked(root, reconsidered, marks, asked)
}

func named(file *string) []string {
	if file == nil || *file == "" {
		return nil
	}

	return []string{*file}
}

func resolved(folder, file string) string {
	absolute := file
	if !filepath.IsAbs(file) {
		absolute = filepath.Join(folder, file)
	}

	if real, err := filepath.EvalSymlinks(absolute); err == nil {
		return real
	}

	return file
}

func quieted() []string {
	var quiet []string

	for _, class := range strings.Split(os.Getenv(quietHooks), ",") {
		if class = strings.TrimSpace(class); class != "" {
			quiet = append(quiet, class)
		}
	}

	return quiet
}

func payloadOf(raw []byte) map[string]any {
	var given map[string]any
	if json.Unmarshal(raw, &given) != nil || given == nil {
		return map[string]any{}
	}

	return given
}

// JournalServe is `journal-serve`: the journal hook kept running, answering over the socket the journal names.
type JournalServe struct{}

func (JournalServe) Names() []string {
	return []string{"journal-serve"}
}

func (JournalServe) Help() help.Help {
	return help.Of("Answer the agent journal's hooks from one running process, over the socket the journal names in $JOURNAL_PLUGIN_SOCKET.").
		Form("journal-serve", "serve until the code it runs changes (started by the journal as a plugin service)").
		In(help.Hooks)
}

func (s JournalServe) Run(in *cli.Input, console cli.Console) (int, error) {
	close, unanswerable := health.ServeOn(os.Getenv(health.Socket), hooksService)
	defer close()

	if unanswerable != "" {
		fmt.Fprintln(console.Err, unanswerable)
		cannotBeAsked.Store(&unanswerable)
	}

	path := os.Getenv(pluginSocket)
	if path == "" {
		return help.Usage(console.Err, s, "The journal names the socket in $"+pluginSocket+"; there is none to listen on."), nil
	}

	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		stopped := cli.StopSignals()
		console.Warn("Cannot listen on " + path + ": " + err.Error() + "; the journal runs each hook as a process of its own until this service is stopped.")
		<-stopped

		return 0, nil
	}

	defer listener.Close()
	os.Chmod(path, 0o600)

	root := workspace.ProjectRoot(firstText(os.Getenv(journalRoot), cwdOf()))
	if noProjectAt(root) {
		return help.Usage(console.Err, s, "This service holds one project's tree, and "+orNothing(root)+
			" is no project: name the project in $"+journalRoot+". Serving it would answer every question about "+
			"every path on this machine."), nil
	}

	started := stamp(root)

	Serves()

	go WarmsWhatItServes(root)

	if judging, listening := Judging(root); listening {
		defer judging.Close()

		forget, _ := warm.Register(root)
		defer forget()

		go serveJudging(judging, root)
	}

	advice := startAdvisers(adviseAsked)
	serve(listener, func() bool { return stamp(root) == started }, advice)

	advice.drained(drainedWithin)

	return 0, nil
}

const drainedWithin = 30 * time.Second

func quiet() cli.Console {
	return cli.Console{Out: io.Discard, Err: io.Discard}
}

func serve(listener net.Listener, current func() bool, advice *advisers) {
	for {
		connection, err := listener.Accept()
		if err != nil {
			if errors.Is(err, net.ErrClosed) {
				return
			}

			continue
		}

		if !current() {
			connection.Close()

			return
		}

		go func() {
			line, _ := bufio.NewReader(connection).ReadString('\n')
			read := payloadOf([]byte(line))

			go heldTheConfigOf(read)

			if drop, asked := lanesAsked(read); asked {
				connection.Write(lanesAnswered(drop))
				connection.Close()

				return
			}

			if health.Asked(read) {
				connection.Write([]byte(health.Answer(hooksService) + "\n"))
				connection.Close()

				return
			}

			if text, apart, _, err := Apart4(cli.InputOf("journal-serve"), quiet(), read); apart {
				if err == nil {
					connection.Write([]byte(text + "\n"))
				}
				connection.Close()

				return
			}

			if moment, four := Moment4Of(read); four {
				given := Stamped(moment)
				connection.Write([]byte(answer4On(given, asText(read["event"])) + "\n"))
				connection.Close()

				expecting(given)
				advice.add(asked{given, judgedAfter(asText(read["event"])), awaited(asText(read["event"]))})

				return
			}

			if _, four := read["v"]; four {
				connection.Write([]byte("\n"))
				connection.Close()

				return
			}

			given := Stamped(read)
			connection.Write([]byte(AnswerNow(given).JSON() + "\n"))
			connection.Close()

			expecting(given)
			advice.add(asked{given, false, true})
		}()
	}
}

var working, advised atomic.Int64

var notes sync.Map

func noted(file *string, what string) {
	if file == nil {
		return
	}

	was, _ := notes.Load(*file)
	had, _ := was.(string)
	notes.Store(*file, had+what+"; ")
}

func NotesLike(name string) string {
	var said []string
	notes.Range(func(key, what any) bool {
		if strings.HasSuffix(key.(string), name) {
			said = append(said, key.(string)+" -> "+what.(string))
		}

		return true
	})

	if len(said) == 0 {
		return "no file of that name was noted at all"
	}

	return strings.Join(said, " | ")
}

func NotesOn(file string) string {
	what, found := notes.Load(file)
	if !found {
		return "nothing happened to it"
	}

	return what.(string)
}

var takenIn sync.Map

func JudgingNow() (int64, int64) {
	return working.Load(), advised.Load()
}

func tookIn(file string) {
	takenIn.Store(file, true)
}

func TookIntoAJudging(file string) bool {
	_, took := takenIn.Load(file)

	return took
}

// asked is one moment the service answered, and where its advice belongs: an event version 4 answered with the gates alone is judged after, into the queue that journal drains, and anything else version 3 asked goes to the queue it hands the service.
type asked struct {
	given  map[string]any
	queued bool
	first  bool
}

// adviser works out ONE AGENT's moments on a goroutine of its own, in the order they came: the service keeps answering while it works, and two checks of one file never run at once, a file belonging to one agent's checkout.
type adviser struct {
	mu      sync.Mutex
	pending []asked
	later   []asked
	wake    chan struct{}
	advise  func(asked)
	working bool
}

// advisers are one adviser per AGENT, which is what keeps one agent's work off another's back.
type advisers struct {
	mu     sync.Mutex
	each   map[int]*adviser
	advise func(asked)
}

func startAdvisers(advise func(asked)) *advisers {
	return &advisers{each: map[int]*adviser{}, advise: advise}
}

func (s *advisers) add(given asked) {
	s.mu.Lock()
	whose := MomentOf(given.given).Agent
	held, known := s.each[whose]
	if !known {
		held = startAdviser(s.advise)
		s.each[whose] = held
	}
	s.mu.Unlock()

	held.add(given)
}

func (s *advisers) drained(within time.Duration) {
	s.mu.Lock()
	held := make([]*adviser, 0, len(s.each))
	for _, each := range s.each {
		held = append(held, each)
	}
	s.mu.Unlock()

	until := time.Now().Add(within)
	for _, each := range held {
		each.drained(time.Until(until))
	}
}

const (
	batchedWithin = 2 * time.Second
	batchedFiles  = 100

	gatheringPause = 100 * time.Millisecond
)

const (
	batchedKey      = "commandments_batched"
	batchedLinesKey = "commandments_batched_lines"
)

func batched(batch []asked) asked {
	if len(batch) == 1 {
		return batch[0]
	}

	var files []any
	lines := map[string]any{}
	stands := batch[0]
	named := false

	for _, held := range batch {
		moment := MomentOf(held.given)
		if moment.File == nil || *moment.File == "" {
			continue
		}

		if !named {
			stands, named = held, true
		}

		files = append(files, *moment.File)

		switch {
		case moment.ChangedWhole:
			lines[*moment.File] = map[string]any{"whole": true}
		case len(moment.Changed) > 0:
			written := make([]any, 0, len(moment.Changed))
			for _, line := range moment.Changed {
				written = append(written, line)
			}

			lines[*moment.File] = map[string]any{"lines": written}
		}
	}

	if len(files) > 1 {
		stands.given[batchedKey] = files
		stands.given[batchedLinesKey] = lines
	}

	return stands
}

func batchedIn(payload map[string]any) []string {
	held, batched := payload[batchedKey].([]any)
	if !batched {
		return nil
	}

	var files []string
	for _, file := range held {
		if named := asText(file); named != "" {
			files = append(files, named)
		}
	}

	return files
}

func batchedLinesOf(payload map[string]any, file string) (git.ChangedLines, bool) {
	held, batched := payload[batchedLinesKey].(map[string]any)
	if !batched {
		return git.ChangedLines{}, false
	}

	for named, carried := range held {
		if named != file && !strings.HasSuffix(filepath.Clean(file), filepath.Clean(named)) {
			continue
		}

		return ChangedIn(map[string]any{changedKey: carried})
	}

	return git.ChangedLines{}, false
}

func startAdviser(advise func(asked)) *adviser {
	a := &adviser{wake: make(chan struct{}, 1), advise: advise}
	go a.work()

	return a
}

func (a *adviser) add(given asked) {
	noted(MomentOf(given.given).File, "the adviser took it in")

	a.mu.Lock()
	if given.first {
		a.pending = append(a.pending, given)
	} else {
		a.later = append(a.later, given)
	}
	a.mu.Unlock()

	select {
	case a.wake <- struct{}{}:
	default:
	}
}

func (a *adviser) work() {
	for range a.wake {
		for {
			a.mu.Lock()
			gathering := a.gathering(time.Now())
			a.mu.Unlock()

			if gathering {
				time.Sleep(gatheringPause)

				continue
			}

			a.mu.Lock()
			batch, held := a.next()
			a.mu.Unlock()

			if !held {
				break
			}

			working.Add(1)
			for _, one := range batch {
				noted(MomentOf(one.given).File, "the adviser advised it")
			}
			a.advise(batched(batch))
			working.Add(-1)
			advised.Add(1)

			a.mu.Lock()
			a.working = false
			a.mu.Unlock()
		}
	}
}

func (a *adviser) drained(within time.Duration) {
	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(20 * time.Millisecond) {
		a.mu.Lock()
		left := len(a.pending) + len(a.later)
		working := a.working
		a.mu.Unlock()

		if left == 0 && !working {
			return
		}
	}
}

func (a *adviser) next() ([]asked, bool) {
	if len(a.pending) > 0 {
		batch := a.pending
		if len(batch) > batchedFiles {
			batch = batch[:batchedFiles]
		}

		a.pending = a.pending[len(batch):]
		a.working = true

		return batch, true
	}

	if len(a.later) > 0 {
		next := a.later[0]
		a.later = a.later[1:]
		a.working = true

		return []asked{next}, true
	}

	return nil, false
}

func (a *adviser) gathering(now time.Time) bool {
	if len(a.pending) == 0 || len(a.pending) >= batchedFiles {
		return false
	}

	stamped := stampOf(a.pending[0].given)
	if stamped == 0 {
		return false
	}

	return now.Sub(time.Unix(0, stamped)) < batchedWithin
}

func stamp(root string) int64 {
	watched := []string{workspace.Config(root), workspace.JSONConfig(root)}

	if self, err := os.Executable(); err == nil {
		watched = append(watched, self)
	}

	filepath.WalkDir(workspace.CustomDir(root), func(path string, entry os.DirEntry, err error) error {
		if err == nil && !entry.IsDir() {
			watched = append(watched, path)
		}

		return nil
	})

	var latest int64

	for _, file := range watched {
		if info, err := os.Stat(file); err == nil && info.ModTime().UnixNano() > latest {
			latest = info.ModTime().UnixNano()
		}
	}

	return latest
}
