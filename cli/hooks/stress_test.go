package hooks

import (
	"encoding/json"
	"flag"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

// The stress harness stands the whole plugin up here — the service, its socket, the warm tree, the workers'
// worktrees — and drives it the way the journal does, so what a consumer's three workers measure can be measured
// without asking them to measure it. Every figure it prints is produced by the same code a real edit runs
// through: the journal's own event on the plugin's own socket, answered by the real service, and a worker's
// hand-in judging run as a terminal run makes it.
//
//	scripts/dev go test ./cli/hooks/ -run TheStress -v -timeout 30m -args -stress -stress.files=800
var (
	stress        = flag.Bool("stress", false, "run the stress harness rather than skipping it")
	stressFiles   = flag.Int("stress.files", 400, "how many source files the project it builds holds")
	stressWorkers = flag.Int("stress.workers", 3, "how many workers edit and hand in at once")
	stressEdits   = flag.Int("stress.edits", 3, "how many files each worker edits")
	stressHandIn  = flag.Bool("stress.hand-in", true, "run each worker's hand-in judging in the same window")
	stressAsks    = flag.Bool("stress.asks", false, "time one ask of 1, 2, 3 and 5 files through the warm bridge instead of the load")
	stressAhead   = flag.Bool("stress.ahead", true, "parse each edited file when its event arrives, together with every file waiting then")
	stressApart   = flag.Duration("stress.apart", 0, "how long a worker waits between its edits; a real agent edits seconds apart, not all at once")
)

// TestTheStress measures the plugin under several workers at once: every worker edits in its own worktree while
// its hand-in judges that whole worktree, which is the shape that made one small edit wait 61 seconds on a
// consumer's tree. It prints what each edit cost — the judging, the share of it that was a bridge parsing, and
// how long the plugin held the event altogether — and how long the queue took to clear after the last edit.
//
// It is skipped unless -stress is passed: it builds a tree, reads it, and runs for minutes.
func TestTheStress(t *testing.T) {
	if !*stress {
		t.Skip("the stress harness runs on request: -args -stress")
	}

	parsed.ahead = *stressAhead
	t.Cleanup(func() { parsed.ahead = true })

	root, data := stressProject(t, *stressFiles, *stressWorkers)
	service := serviceAt(t, root)
	stressBridge(t, git.ProjectRoot(root))

	settings, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	// The base is read the way the first hook of a session has it read, and waited for: every figure below is a
	// figure of a WARM tree, which is what a consumer measures once its session is under way.
	warming := time.Now()
	Warming(root, settings)

	if !heldWithin(project, branch, 15*time.Minute) {
		t.Fatalf("no base was held after %v", time.Since(warming).Round(time.Second))
	}
	t.Cleanup(func() { kept.Drop(project, branch) })

	read := time.Since(warming)
	t.Logf("the base of %d files was read in %v", *stressFiles, read.Round(time.Millisecond))

	drained(t, data)

	// Every worker edits in its own worktree and hands in at the same time, which is the window the figures are
	// about: a hand-in judges a whole worktree, and until it stopped sharing the bridge every edit queued behind
	// one.
	fired := time.Now()

	var work sync.WaitGroup
	var last struct {
		sync.Mutex
		at time.Time
	}

	for worker := 1; worker <= *stressWorkers; worker++ {
		work.Add(1)

		go func(worker int) {
			defer work.Done()

			for edit := 0; edit < *stressEdits; edit++ {
				if edit > 0 {
					time.Sleep(*stressApart)
				}

				sendEvent(t, service, stressEvent(worker, edit, stressEdit(t, root, worker, edit)))

				last.Lock()
				last.at = time.Now()
				last.Unlock()
			}

			if *stressHandIn {
				stressHandInBy(t, root, worker)
			}
		}(worker)
	}

	work.Wait()

	answers, landed := stressAnswers(t, data, *stressWorkers**stressEdits, 15*time.Minute)

	// From the last EDIT, never from the last hand-in: a hand-in takes seconds of its own after the edits are in,
	// and counted from there the queue read as having cleared before the edits were sent.
	t.Logf("%d edits %v apart and %d hand-ins were sent in %v; the last answer landed %v after the last edit",
		*stressWorkers**stressEdits, *stressApart, stressHandIns(), time.Since(fired).Round(time.Millisecond),
		landed.Sub(last.at).Round(time.Millisecond))

	stressReport(t, answers)
}

// handIns is how many hand-in judgings ran, for the line that says so.
func stressHandIns() int {
	if !*stressHandIn {
		return 0
	}

	return *stressWorkers
}

// report prints what every answer cost, and the spread, which is what a reader of a stress run is after.
func stressReport(t *testing.T, answers []map[string]any) {
	t.Helper()

	if len(answers) == 0 {
		t.Fatal("not one edit was answered")
	}

	var judged, reading, whole []int64
	for _, answer := range answers {
		judged = append(judged, said(answer, "judged_ms"))
		reading = append(reading, said(answer, "read_ms"))
		whole = append(whole, said(answer, "judged_total_ms"))
	}

	t.Logf("%-24s %8s %8s %8s", "", "judged", "read", "total")
	for at := range answers {
		t.Logf("%-24s %8d %8d %8d", fmt.Sprintf("edit %d", at+1), judged[at], reading[at], whole[at])
	}

	for named, column := range map[string][]int64{"judged_ms": judged, "read_ms": reading, "judged_total_ms": whole} {
		sort.Slice(column, func(a, b int) bool { return column[a] < column[b] })
		t.Logf("%-16s least %6d  median %6d  worst %6d", named, column[0], column[len(column)/2], column[len(column)-1])
	}
}

// said is one duration an answer carries, nought where it carries none.
func said(answer map[string]any, named string) int64 {
	said, _ := answer[named].(float64)

	return int64(said)
}

// stressBridge keeps the project's language bridge up, which is what the journal's own mypy-serve does. Without
// it every edit starts a bridge of its own and pays its whole start-up, and the figures then measure a start-up
// that no session pays: it is the single biggest thing a harness can get wrong about this plugin.
func stressBridge(t *testing.T, project string) {
	t.Helper()

	bridged, needed, err := scan.Python.Reach.Own(scan.Python, []string{project})
	if err != nil || !needed {
		t.Fatalf("the python bridge cannot be kept up here: needed %v, %v", needed, err)
	}
	t.Cleanup(func() { bridged.Close() })

	path := bridge.ServiceSocket(scan.Python.Kind, project)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		t.Fatalf("the bridge service cannot listen on %s: %v", path, err)
	}
	t.Cleanup(func() { listener.Close() })

	go bridge.Answer(listener, bridged)
}

// serviceAt starts the real service for the project, on the socket the journal names, and hands back that
// socket: this is the plugin as the journal runs it, in this process rather than beside it.
func serviceAt(t *testing.T, root string) string {
	t.Helper()

	// A unix socket's path is capped at about a hundred characters, and a test's own folder is most of that
	// before the name: a socket under it could not be listened on at all, and the service then waits for its
	// stop rather than serving, which reads as a service that never came up.
	socket := fmt.Sprintf("/tmp/cc-stress-%d.sock", os.Getpid())
	t.Setenv(pluginSocket, socket)
	t.Cleanup(func() { os.Remove(socket) })

	// The service names the project from the folder it runs in, as the journal starts it in the project's own.
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.Chdir(was) })

	go func() { JournalServe{}.Run(cli.InputOf("journal-serve"), quiet()) }()

	for until := time.Now().Add(30 * time.Second); time.Now().Before(until); time.Sleep(50 * time.Millisecond) {
		if connection, err := net.Dial("unix", socket); err == nil {
			connection.Close()

			return socket
		}
	}

	t.Fatalf("the service never listened on %s", socket)

	return ""
}

// told sends one of the journal's events on the plugin's socket and reads the answer, which is exactly what the
// journal does: the answer comes back at once and the judging is done after it, so what it costs is counted in
// the queue and not here.
func sendEvent(t *testing.T, socket string, event map[string]any) {
	t.Helper()

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	line, err := json.Marshal(event)
	if err != nil {
		t.Fatal(err)
	}

	if _, err := connection.Write(append(line, '\n')); err != nil {
		t.Fatal(err)
	}

	answer := make([]byte, 64*1024)
	if _, err := connection.Read(answer); err != nil {
		t.Fatal(err)
	}
}

// stressEvent is the journal's file_edited moment for one worker's edit, as the journal writes it: the lines the
// edit added among them, since a sin on a line the edit did not touch is not that edit's doing and is not told.
func stressEvent(worker, edit int, added []any) map[string]any {
	return map[string]any{
		"v": 1, "event": "file_edited", "session": fmt.Sprintf("s%d", worker), "environment": "main", "agent": worker,
		"data": map[string]any{
			"path":  fmt.Sprintf(".journal/worktrees/worker-%d/shop/thing%d.py", worker, edit),
			"kind":  "edit",
			"added": len(added), "removed": 0, "tool_use": edit + 1,
			"lines": added,
		},
	}
}

// stressAppended is what an edit adds: a method carrying sins of its own, so the edit really has something to be
// told about. An edit that changes a line no rule flags is answered with nothing at all, which would measure the
// queue and not the judging.
const stressAppended = `
    def deepened(self, order, rate=None):
        if order is not None:
            for line in order.lines:
                if line.total > 0:
                    if rate is not None:
                        return line.total * rate
                    else:
                        return line.total
        return 0
`

// stressEdit writes the edit the event announces, so the file the plugin reads really has moved, and answers
// with the lines it added as the journal reports them.
func stressEdit(t *testing.T, root string, worker, edit int) []any {
	t.Helper()

	path := filepath.Join(root, ".journal", "worktrees", fmt.Sprintf("worker-%d", worker), "shop", fmt.Sprintf("thing%d.py", edit))

	was := stressFile(edit)
	if err := os.WriteFile(path, []byte(was+stressAppended), 0o644); err != nil {
		t.Fatal(err)
	}

	var added []any
	from := len(strings.Split(strings.TrimRight(was, "\n"), "\n"))

	for at, text := range strings.Split(strings.TrimRight(stressAppended, "\n"), "\n") {
		added = append(added, map[string]any{"line_type": "add", "line": from + at + 1, "text": text})
	}

	return added
}

// handIn runs the judging a worker runs on its way out: a terminal run in its own worktree, which asks the
// service for the tree rather than reading one.
func stressHandInBy(t *testing.T, root string, worker int) {
	t.Helper()

	worktree := filepath.Join(root, ".journal", "worktrees", fmt.Sprintf("worker-%d", worker))

	began := time.Now()
	code, err := (judge.Command{In: worktree}).Run(cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=2"), quiet())
	t.Logf("worker %d handed in: exit %d, err %v, in %v", worker, code, err, time.Since(began).Round(time.Millisecond))
}

// heldWithin waits for the base, which is read in the background as the first hook of a session has it read.
func heldWithin(project, branch string, within time.Duration) bool {
	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(200 * time.Millisecond) {
		if _, held := kept.Holds(project, branch); held {
			return true
		}
	}

	return false
}

// stressAnswers waits for the plugin's answers and says when the last of them landed, which is the queue clearing.
func stressAnswers(t *testing.T, data string, wanted int, within time.Duration) ([]map[string]any, time.Time) {
	t.Helper()

	queue, _ := Queue4In(data)

	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(100 * time.Millisecond) {
		files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
		if len(files) >= wanted {
			return drained(t, data), time.Now()
		}
	}

	return drained(t, data), time.Now()
}

// stressProject is a project of the size asked for, with a worktree per worker as the journal cuts them, and the
// plugin's data folder. Every rule the project's languages have judges it, as a consumer's run is judged.
func stressProject(t *testing.T, files, workers int) (root, data string) {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data = t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	written := map[string]string{
		".commandments/config.json": `{"paths": ["shop"]}`,
		"shop/__init__.py":          "",
		"shop/base.py":              "class Base:\n    number = 0\n",
	}
	for at := 0; at < files; at++ {
		written[fmt.Sprintf("shop/thing%d.py", at)] = stressFile(at)
	}

	for path, text := range written {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	commands := [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
	}
	for worker := 1; worker <= workers; worker++ {
		commands = append(commands, []string{"worktree", "add", "-q", "-b", fmt.Sprintf("worker-%d", worker),
			filepath.Join(root, ".journal", "worktrees", fmt.Sprintf("worker-%d", worker))})
	}

	for _, command := range commands {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	return root, data
}

// stressFile is one file of the project: a class of the shape a real one has, with sins real rules find, and
// imports of its neighbours so the project has a module graph with DEPTH.
//
// The depth is not decoration. A type checker asked about one changed file re-checks that module and everything
// that imports it, so what an edit costs depends on the graph above the file, not on how many files the tree
// holds. A generated project whose files import nothing answered in 350ms whatever its size, while a real tree
// of real code answered in 0.1 to 5.2 seconds for the same shape of edit: a harness without depth measures the
// plugin against code nobody writes.
func stressFile(at int) string {
	imports, uses := "", ""

	for back := 1; back <= 3 && at-back >= 0; back++ {
		imports += fmt.Sprintf("from shop.thing%d import Thing%d\n", at-back, at-back)
		uses += fmt.Sprintf("        self.near%d = Thing%d(name)\n", at-back, at-back)
	}

	return fmt.Sprintf(`from shop.base import Base
%s

class Thing%d(Base):
    def __init__(self, name: str = "", total: int = 0):
        self.name = name
        self.total = total
%s
    def priced(self, order, rate=None):
        if order is not None:
            for line in order.lines:
                if line.total > 0:
                    if rate is not None:
                        return line.total * rate
                    else:
                        return line.total
        return 0

    def named(self):
        return self.name or "unknown"
`, imports, at, uses)
}

// TestWhatAnAskCostsByItsFiles answers the one question the queue's design turns on: how much of what an edit
// pays is per ASK rather than per file. A bridge pays a fixed cost for every ask — the round trip, and mypy
// re-checking its module graph — and a per-file cost on top. Where the fixed part dominates, the edits waiting
// in the queue should be handed over in ONE ask, and three edits then cost barely more than one; where it does
// not, coalescing them buys nothing and the queue is right as it stands.
//
//	scripts/dev go test ./cli/hooks/ -run WhatAnAskCosts -v -args -stress -stress.asks -stress.files=1000
func TestWhatAnAskCostsByItsFiles(t *testing.T) {
	if !*stress || !*stressAsks {
		t.Skip("the ask measurement runs on request: -args -stress -stress.asks")
	}

	root, _ := stressProject(t, *stressFiles, 1)
	project := git.ProjectRoot(root)
	stressBridge(t, project)

	// Warm as a session is warm: the first ask of all pays what a session pays once, and no measurement below
	// is that ask.
	if _, err := scan.Walk(filesOf(t, root, 1), source.Excluded{}).Load(); err != nil {
		t.Fatalf("the python bridge cannot answer here: %v", err)
	}

	// One file again at the end, after nine: a reader comparing the two sees that the first ask of all is not
	// what makes a single file look cheap or dear.
	for _, many := range []int{1, 2, 3, 5, 9, 1} {
		asked := filesOf(t, root, many)

		// Every file is touched first, so the ask is one of files that really have moved, as an edit's is.
		for _, file := range asked {
			stressTouch(t, file)
		}

		began := time.Now()
		codebase, err := scan.Walk(asked, source.Excluded{}).Load()
		took := time.Since(began)

		if err != nil || codebase == nil {
			t.Fatalf("an ask of %d files: %v", many, err)
		}

		t.Logf("%d file(s) in one ask: %v, %v each", many, took.Round(time.Millisecond), (took / time.Duration(many)).Round(time.Millisecond))
	}
}

// filesOf are that many of the project's files, the ones an edit would have changed.
func filesOf(t *testing.T, root string, many int) []string {
	t.Helper()

	var asked []string
	for at := 0; at < many; at++ {
		asked = append(asked, filepath.Join(root, "shop", fmt.Sprintf("thing%d.py", at)))
	}

	return asked
}

// stressTouch writes the file anew, so the bridge is asked about a file that has really moved.
func stressTouch(t *testing.T, path string) {
	t.Helper()

	was, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(path, append(was, []byte("\n    # touched\n")...), 0o644); err != nil {
		t.Fatal(err)
	}
}
