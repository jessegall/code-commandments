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

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/help"
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

	_ = queue.Tell(answerWith(moment.given, not(isGate), true), asText(moment.given["env"]))
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

	for _, hook := range ForProject(event.Root) {
		if runs(hook) && !slices.Contains(quiet, hook.Class()) {
			responses = append(responses, Answer(hook, event))
		}
	}

	merged := Merge(responses)
	answer := answerOf(merged)

	if !reportsSins || !moment.IsPostToolUse() && !moment.IsStop() {
		return answer
	}

	settlement := settled(moment, event.Root, merged.Activity, merged.Judged)
	answer.Raises = append(answer.Raises, settlement.Raises(event.Root)...)
	answer.Settles = settlement.Settles()
	answer.Repented = settlement.Repented

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
		swept, files := swept(record, root)
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

	serve(listener, func() bool { return stamp(root) == started }, startAdviser(adviseAsked))

	return 0, nil
}

// quiet is a console whose words go nowhere: the service answers on its socket, and what a command would have
// printed is no part of that answer.
func quiet() cli.Console {
	return cli.Console{Out: io.Discard, Err: io.Discard}
}

// serve answers each connection's gates at once and hands its moment to the adviser, so no gate ever waits on a
// sin check, until a connection finds what it runs no longer current: that one it hangs up unanswered.
func serve(listener net.Listener, current func() bool, advice *adviser) {
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
				advice.add(asked{given, judgedAfter(asText(read["event"]))})

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

			advice.add(asked{given, false})
		}()
	}
}

// asked is one moment the service answered, and where its advice belongs: an event version 4 answered with the
// gates alone is judged after, into the queue that journal drains, and anything else version 3 asked goes to the
// queue it hands the service.
type asked struct {
	given  map[string]any
	queued bool
}

// adviser works out each moment's advice on one goroutine of its own, in the order the moments came: the service
// keeps answering while it works, and two checks of one file never run at once.
type adviser struct {
	mu      sync.Mutex
	pending []asked
	wake    chan struct{}
	advise  func(asked)
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
	a.pending = append(a.pending, given)
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
			if len(a.pending) == 0 {
				a.mu.Unlock()

				break
			}
			given := a.pending[0]
			a.pending = a.pending[1:]
			a.mu.Unlock()

			a.advise(given)
		}
	}
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
