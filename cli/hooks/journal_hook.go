package hooks

import (
	"bufio"
	"encoding/json"
	"errors"
	"io"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"sync"
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
	// quietHooks names hooks, comma-separated, the journal has silenced.
	quietHooks = "COMMANDMENTS_QUIET_HOOKS"
	// pluginData is the plugin's own data folder, where the announced sins are kept.
	pluginData = "JOURNAL_PLUGIN_DATA"
	// advising marks the detached run that works out a moment's advice after the answer went out.
	advising = "COMMANDMENTS_ADVISING"
	// pluginSocket is where the journal expects the plugin's service to listen.
	pluginSocket = "JOURNAL_PLUGIN_SOCKET"
	// journalRoot is the project the journal runs for, which is the main checkout and never a worker's own tree.
	journalRoot = "JOURNAL_ROOT"
	// hooksService is the name the manifest gives this service, which is the name it answers the journal's health
	// question under: the journal restarts the service that went quiet, so the answer says which one answered.
	hooksService = "hooks"
)

// JournalHook is `journal-hook`: the entry point the agent journal's plugin calls for every moment.
type JournalHook struct{}

// Names are the verbs it answers to.
func (JournalHook) Names() []string {
	return []string{"journal-hook"}
}

// Help documents it.
func (JournalHook) Help() help.Help {
	return help.Of("The agent journal's entry point — reads one journal hook payload from stdin, runs every registered handler, and answers in the journal's shape.").
		Form("journal-hook", "answer the moment on stdin (wired by the journal plugin; you rarely run this by hand)").
		In(help.Hooks)
}

// Run answers the moment on stdin at once and works out its advice after, when the journal keeps a queue.
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

// AnswerNow is the answer the journal waits on: the gates alone when a queue will carry the advice, else
// every hook.
func AnswerNow(given map[string]any) JournalAnswer {
	if _, queued := QueueFromEnvironment(); queued {
		return answerWith(given, isGate, false)
	}

	return answerWith(given, every, true)
}

// answer4On is the service's answer to one of version 4's events: an event judged after carries the gates alone,
// since the journal waits on every answer it asks for and a judge does not fit the window it allows; the rest of
// that moment is handed to the adviser. Every other event carries what the handlers found, exactly as the program
// would have answered it.
func answer4On(given map[string]any, event string) string {
	if judgedAfter(event) {
		return answerWith(given, isGate, false).Line4()
	}

	return answerWith(given, every, true).Line4()
}

// adviseNow works out the moment's advice, every hook but the gates, and tells it to the queue.
func adviseNow(given map[string]any) {
	if queue, queued := QueueFromEnvironment(); queued {
		queue.Tell(answerWith(given, not(isGate), true), MomentOf(given))
	}
}

// adviseAsked works out the advice of a moment the service answered and writes it where that journal reads it.
func adviseAsked(moment asked) {
	if !moment.queued {
		adviseNow(moment.given)

		return
	}

	queue, queued := Queue4In(os.Getenv(pluginData))
	if !queued {
		return
	}

	// The agent the moment came from, so this answer reaches that agent and no other.
	_ = queue.Tell(answerWith(moment.given, not(isGate), true), asText(moment.given["env"]), MomentOf(moment.given).Agent)
}

// adviseDetached works the advice out in a run of its own, so the journal is not kept waiting on it. The run reads
// the moment from a file it inherits, never from a pipe: a pipe is fed by this process, which answers and ends at
// once, so the run would read nothing and advise nothing.
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

// momentFile is the moment written to a file and opened from its start, for a run to inherit as its input. Its name
// is gone from the disk before the run reads it, so nothing is left behind.
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

// answerWith is the hooks the filter keeps answering the moment, in the journal's shape; after a tool call
// it also raises what the edit found and repented, when it reports sins. A payload that names no moment
// is none, and gets no answer.
func answerWith(given map[string]any, runs func(Hook) bool, reportsSins bool) JournalAnswer {
	moment := MomentOf(given)
	if moment.Event == "" {
		return JournalAnswer{}
	}

	cwd := moment.Cwd
	if info, err := os.Stat(cwd); err != nil || !info.IsDir() {
		cwd, _ = os.Getwd()
	}

	event := NewEvent(moment.HookPayload(), workspace.ProjectRoot(cwd))
	workspace.At(event.Root, "").RelocateSessions()
	quiet := quieted()

	var responses []Response

	// What the judging itself costs, which is the work below and not the wait above it: an event waits its turn,
	// since one event is judged at a time, and a reader given only the total cannot tell the two apart.
	judging := time.Now()

	for _, hook := range ForProject(event.Root) {
		if runs(hook) && !slices.Contains(quiet, hook.Class()) {
			responses = append(responses, Answer(hook, event))
		}
	}

	spent := time.Since(judging)

	merged := Merge(responses)
	answer := answerOf(merged)
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	if !reportsSins || !moment.IsPostToolUse() && !moment.IsStop() {
		return answer
	}

	settlement := settled(moment, event.Root, merged.Activity, merged.Judged)
	answer.Raises = append(answer.Raises, settlement.Raises(event.Root)...)
	answer.Settles = settlement.Settles()
	answer.Repented = settlement.Repented
	answer.JudgedMs = milliseconds(spent)
	answer.JudgedTotalMs = heldFor(given, answer.JudgedMs)
	answer.ReadMs = merged.ReadMs

	return answer
}

// settled is what the edit found and repented against the sins already announced, kept in the plugin's
// data folder and shown on its dashboard; with no folder, every touched sin is new.
func settled(moment Moment, root string, marks []SinMark, read []string) Settlement {
	data := os.Getenv(pluginData)
	reconsidered := append(read, named(moment.File)...)

	if data == "" {
		return Forgotten().Settle(root, reconsidered, marks)
	}

	record := AnnouncedIn(data, moment)

	// Going idle judges the files that still carry a mark, so one whose sins are gone turns green even where
	// nothing edited that file again.
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

	return record.Settle(root, reconsidered, marks)
}

// named is the file the moment names, or none where it names a tool that writes without saying which file: a
// shell command that rewrites a file names nothing, and the sins of that file would otherwise never be
// reconsidered however often it is judged.
func named(file *string) []string {
	if file == nil || *file == "" {
		return nil
	}

	return []string{*file}
}

// resolved is the file with its links resolved, a relative one read from the folder the moment happened in.
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

// quieted are the hooks the journal has silenced, by class.
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

// JournalServe is `journal-serve`: the journal hook kept running, answering over the socket the journal
// names.
type JournalServe struct{}

// Names are the verbs it answers to.
func (JournalServe) Names() []string {
	return []string{"journal-serve"}
}

// Help documents it.
func (JournalServe) Help() help.Help {
	return help.Of("Answer the agent journal's hooks from one running process, over the socket the journal names in $JOURNAL_PLUGIN_SOCKET.").
		Form("journal-serve", "serve until the code it runs changes (started by the journal as a plugin service)").
		In(help.Hooks)
}

// Run serves until what it runs changes: then it hangs up unanswered, so the caller runs the command
// itself, and ends, so the journal starts it again on the new code. A socket it cannot listen on, as a Windows
// binary cannot on MSYS2's, leaves it waiting for the stop, so the journal does not start it again.
func (s JournalServe) Run(in *cli.Input, console cli.Console) (int, error) {
	// Before anything else, so the journal's health question is answered from the moment this process is up and in
	// every path below, the one that cannot listen on the plugin's socket included: a service waiting for its stop
	// is still a service that is up.
	defer health.Serve(hooksService)()

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

	cwd, _ := os.Getwd()
	root := workspace.ProjectRoot(cwd)
	started := stamp(root)

	// This process outlives every hook it answers, so a tree it reads once serves every later edit: the mark can
	// then ask the rules that read more than the file they report in, which a run judging one file cannot.
	Serves()

	// And a judging asked for from a terminal is answered against that same tree, on a socket named from the
	// project so a run that was handed nothing still finds this service. One it cannot listen on costs only
	// that: such a run reads the tree itself, as it always did.
	if judging, listening := Judging(root); listening {
		defer judging.Close()

		// What this service serves, written where any run can read it: a socket is named from the project, so a
		// run that does not know the project cannot name the socket, and this is how it finds both.
		forget, _ := warm.Register(root)
		defer forget()

		go serveJudging(judging, root)
	}

	advice := startAdvisers(adviseAsked)
	serve(listener, func() bool { return stamp(root) == started }, advice)

	// What it has answered but not yet judged is worked out before it goes. The service ends when the code it
	// runs changes, and its pending advice went with it: those moments were answered with the gates alone, as
	// every edit is, so the sins in them were never told at all and nothing said so.
	advice.drained(drainedWithin)

	return 0, nil
}

// drainedWithin is how long a service ending waits for the advice it still holds. Past it the journal has been
// kept from starting the new code long enough, and what is left is one more hook's worth of sins untold rather
// than a service that will not go.
const drainedWithin = 30 * time.Second

// quiet is a console whose words go nowhere: the service answers on its socket, and what a command would have
// printed is no part of that answer.
func quiet() cli.Console {
	return cli.Console{Out: io.Discard, Err: io.Discard}
}

// serve answers each connection's gates at once and hands its moment to the adviser, so no gate ever waits on a
// sin check, until a connection finds what it runs no longer current: that one it hangs up unanswered.
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

			// How this service is, asked on the socket it already answers the journal's events on. It is a fact
			// this process knows by running, so it is said at once and nothing of the moment is read for it.
			if health.Asked(read) {
				connection.Write([]byte(health.Answer(hooksService) + "\n"))
				connection.Close()

				return
			}

			// An event that is not a moment of the agent's work is answered in full, through the same path the
			// program takes: this is where the service used to fall through to version 3's answer, which version 4
			// drops whole.
			if text, apart, _, err := Apart4(cli.InputOf("journal-serve"), quiet(), read); apart {
				if err == nil {
					connection.Write([]byte(text + "\n"))
				}
				connection.Close()

				return
			}

			// Version 4 asks on this socket inside a window the journal sets, so the gates answer from what they
			// already know and everything else is worked out after the answer, as it is for version 3.
			if moment, four := Moment4Of(read); four {
				given := Stamped(moment)
				connection.Write([]byte(answer4On(given, asText(read["event"])) + "\n"))
				connection.Close()

				// The file is parsed from here, before this moment's turn in the queue, and together with every
				// other file waiting: the parse is nearly all of what an edit pays and a bridge's cost is per
				// ask, so the edits waiting together cost one ask between them rather than one each.
				expecting(given)
				advice.add(asked{given, judgedAfter(asText(read["event"])), awaited(asText(read["event"]))})

				return
			}

			// Any other line of version 4's is answered in version 4's shape, which an empty answer is: version 4
			// drops an answer carrying version 3's keys whole, and its own events are not all moments of the
			// agent's work. Version 3 names its moments with a hook of its own, `hook.PreToolUse`, and carries no
			// protocol version, so the version is what tells the two apart.
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

// asked is one moment the service answered, and where its advice belongs: an event version 4 answered with the
// gates alone is judged after, into the queue that journal drains, and anything else version 3 asked goes to the
// queue it hands the service.
type asked struct {
	given  map[string]any
	queued bool
	// first says this moment's advice goes before the rest. An edit's does: the agent is waiting to be told
	// about the file it just wrote, and a heartbeat's advice, which nothing waits on, used to go ahead of it.
	first bool
}

// adviser works out ONE AGENT's moments on a goroutine of its own, in the order they came: the service keeps
// answering while it works, and two checks of one file never run at once, a file belonging to one agent's
// checkout. One per agent is advisers, above.
type adviser struct {
	mu      sync.Mutex
	pending []asked
	// later are the moments nothing waits on, worked out once no edit is waiting. A session raises a heartbeat
	// every minute for every open agent and one at every stop, and each of those settles every sin the session
	// has announced: measured here at 2.5 seconds apiece, and with eighteen of them ahead of nine edits the
	// last edit was answered 48 seconds after it landed.
	later  []asked
	wake   chan struct{}
	advise func(asked)
	// working says a moment's advice is being worked out right now, so a service ending waits for it as well as
	// for the ones still queued.
	working bool
}

// advisers are one adviser per AGENT, which is what keeps one agent's work off another's back.
//
// There used to be one for the whole project, and a moment then waited behind every moment before it, whoever
// raised it. Measured with three workers, a fourth, the main agent and a 233-test run going at once on a
// consumer: judged_total 27955 ms for 143 ms of its own work, and one worker's answer arrived 3 min 51 s after
// its write, by which time the worker had ended and its sin was never told.
//
// Per agent and not per file, because an agent's own moments must still be worked out in order: two checks of
// one file must never run at once, and a file belongs to the checkout of one agent. So each agent keeps the
// ordering it had and loses the waiting it never owed.
type advisers struct {
	mu     sync.Mutex
	each   map[int]*adviser
	advise func(asked)
}

// startAdvisers is one adviser per agent, each started as that agent is first seen.
func startAdvisers(advise func(asked)) *advisers {
	return &advisers{each: map[int]*adviser{}, advise: advise}
}

// add gives the moment to its own agent's adviser, starting one where that agent has none yet.
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

// drained waits for the advice every agent still holds, which is what a service owes before it ends.
func (s *advisers) drained(within time.Duration) {
	s.mu.Lock()
	held := make([]*adviser, 0, len(s.each))
	for _, each := range s.each {
		held = append(held, each)
	}
	s.mu.Unlock()

	// Every agent inside the one window, since they work at the same time: giving each the whole window in turn
	// would let a service that owes five agents wait five windows before it goes.
	until := time.Now().Add(within)
	for _, each := range held {
		each.drained(time.Until(until))
	}
}

// batchedWithin is how long an agent's adviser gathers that agent's edits before judging them together, and
// batchedFiles the most it gathers at once however fast they come.
//
// The window is the saving. Measured with eight agents writing at once, every one answered from a warm tree
// with no reading at all: a judging of one file took 3.7 seconds of rules, so an agent's twelve edits judged one
// after another cost it 44 seconds before it heard about the last of them. Those twelve in one pass cost about
// one pass.
//
// NOTHING waits on the window. The event's own answer went out with the gates alone before the moment was ever
// gathered, so this is a timer and not a block, and it is only ever entered while the adviser has work in hand
// or an edit already waiting.
const (
	batchedWithin = 2 * time.Second
	batchedFiles  = 100

	// gatheringPause is how often the adviser looks again while it gathers, short enough that a lone edit is
	// answered well inside the window.
	gatheringPause = 100 * time.Millisecond
)

// batchedKey is where the moment that stands for a batch carries every file of it, and batchedLinesKey the lines
// each of those edits wrote, by the file the journal named.
const (
	batchedKey      = "commandments_batched"
	batchedLinesKey = "commandments_batched_lines"
)

// batched is the one moment that stands for the batch: the first of it, carrying every file the batch holds and
// the lines each of those edits wrote. They are all of one agent, so one answer naming all of them is what that
// agent is owed, and judging each moment would judge the same files again for every one.
//
// The LINES travel with the files, read from the MOMENT as the payload reads them. They live in the moment's own
// data; the key they end up under belongs to the hook payload built from it. Taken from the wrong one they were
// always absent, every file but the first fell back to asking git, and git answers nothing about work a worktree
// has not committed: the sins of those files were found and then read as nobody's doing.
func batched(batch []asked) asked {
	if len(batch) == 1 {
		return batch[0]
	}

	var files []any
	lines := map[string]any{}

	for _, held := range batch {
		moment := MomentOf(held.given)
		if moment.File == nil || *moment.File == "" {
			continue
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

	stands := batch[0]
	if len(files) > 1 {
		stands.given[batchedKey] = files
		stands.given[batchedLinesKey] = lines
	}

	return stands
}

// batchedIn are the files the moment stands for, as the journal named them, and none where it stands alone.
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

// batchedLinesOf are the lines the batch's own edit of that file wrote, and false where it carries none for it.
//
// Matched by the file's own tail, since the batch carries the paths the journal NAMED and the caller asks about
// the resolved ones: the journal names a path relative to a checkout and the judging works in real paths.
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

// startAdviser is an adviser giving each moment to advise, its goroutine started.
func startAdviser(advise func(asked)) *adviser {
	a := &adviser{wake: make(chan struct{}, 1), advise: advise}
	go a.work()

	return a
}

// add queues the moment's advice and wakes the worker.
func (a *adviser) add(given asked) {
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

			// Gathered for the window before it goes over, so an agent's run of edits is judged in one pass
			// rather than a pass each. Nothing waits on it: the event's own answer went out before the moment
			// was ever gathered.
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

			// ONE moment is worked out for the batch, carrying every file of it: they are all of one agent, so
			// one answer naming all of them is what that agent is owed.
			a.advise(batched(batch))

			a.mu.Lock()
			a.working = false
			a.mu.Unlock()
		}
	}
}

// drained waits for the advice this adviser still holds to be worked out, which is what a service owes before it
// ends.
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

// next are the moments to work out together, which for this agent's edits is as many as are waiting, up to the
// cap: they are judged in one pass over one view. An edit comes before anything else, the agent being the one
// waiting to hear about the file it just wrote. The caller holds the lock.
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

// gathering says this agent has an edit waiting that is young enough for more of its own to join it, and that
// the batch is not full. The caller holds the lock.
//
// A moment carrying no stamp is gathered for nothing and goes at once. Read through the moment's own clock it
// would be brand new every time it was asked, so the window never closed and nothing was ever judged: the
// adviser gathered for ever. The service stamps every moment as it arrives, so this is the answer for a moment
// that reached the adviser another way.
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

// stamp is the latest change to what a running service runs: the executable, the project's config and its
// own rules.
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
