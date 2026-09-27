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

	console.Write(AnswerNow(given).JSON() + "\n")

	if _, queued := QueueFromEnvironment(); queued {
		adviseDetached(raw)
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

// adviseNow works out the moment's advice, every hook but the gates, and tells it to the queue.
func adviseNow(given map[string]any) {
	if queue, queued := QueueFromEnvironment(); queued {
		queue.Tell(answerWith(given, not(isGate), true), MomentOf(given))
	}
}

// adviseDetached works the advice out in a run of its own, so the journal is not kept waiting on it.
func adviseDetached(raw []byte) {
	self, err := os.Executable()
	if err != nil {
		return
	}

	run := exec.Command(self, "journal-hook")
	run.Stdin = strings.NewReader(string(raw))
	run.Env = append(os.Environ(), advising+"=1")
	detach(run)

	if run.Start() == nil {
		run.Process.Release()
	}
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

	if !moment.IsPostToolUse() || !reportsSins {
		return answer
	}

	answer.Raises = append(answer.Raises, raised(moment, event.Root, merged.Activity)...)

	return answer
}

// raised settles what the edit found and repented against the sins already announced, kept in the plugin's
// data folder and shown on its dashboard; with no folder, every touched sin is new.
func raised(moment Moment, root string, marks []SinMark) []Raise {
	data := os.Getenv(pluginData)
	if data == "" {
		found, repented := Forgotten().Settle(root, moment.File, marks)

		return raises(root, found, repented)
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
	found, repented := AnnouncedIn(data).Settle(root, moment.File, marks)

	return raises(root, found, repented)
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
// itself, and ends, so the journal starts it again on the new code.
func (s JournalServe) Run(in *cli.Input, console cli.Console) (int, error) {
	path := os.Getenv(pluginSocket)
	if path == "" {
		return help.Usage(console.Err, s, "The journal names the socket in $"+pluginSocket+"; there is none to listen on."), nil
	}

	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		console.Warn("Cannot listen on " + path + ": " + err.Error())

		return 1, nil
	}

	defer listener.Close()
	os.Chmod(path, 0o600)

	cwd, _ := os.Getwd()
	root := workspace.ProjectRoot(cwd)
	started := stamp(root)

	serve(listener, func() bool { return stamp(root) == started }, startAdviser(adviseNow))

	return 0, nil
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
			given := payloadOf([]byte(line))

			connection.Write([]byte(AnswerNow(given).JSON() + "\n"))
			connection.Close()

			advice.add(given)
		}()
	}
}

// adviser works out each moment's advice on one goroutine of its own, in the order the moments came: the service
// keeps answering while it works, and two checks of one file never run at once.
type adviser struct {
	mu      sync.Mutex
	pending []map[string]any
	wake    chan struct{}
	advise  func(map[string]any)
}

// startAdviser is an adviser giving each moment to advise, its goroutine started.
func startAdviser(advise func(map[string]any)) *adviser {
	a := &adviser{wake: make(chan struct{}, 1), advise: advise}
	go a.work()

	return a
}

// add queues the moment's advice and wakes the worker.
func (a *adviser) add(given map[string]any) {
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
