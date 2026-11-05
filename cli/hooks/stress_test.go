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

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/warm"
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

	root, data := stressProject(t, *stressFiles, *stressWorkers)
	service := serviceAt(t, root)

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

	for worker := 1; worker <= *stressWorkers; worker++ {
		work.Add(1)

		go func(worker int) {
			defer work.Done()

			for edit := 0; edit < *stressEdits; edit++ {
				sendEvent(t, service, stressEvent(worker, edit, stressEdit(t, root, worker, edit)))
			}

			if *stressHandIn {
				stressHandInBy(t, root, worker)
			}
		}(worker)
	}

	work.Wait()
	sent := time.Since(fired)

	answers, cleared := stressAnswers(t, data, *stressWorkers**stressEdits, 15*time.Minute)

	t.Logf("%d edits and %d hand-ins were sent in %v; the queue cleared %v after the last of them",
		*stressWorkers**stressEdits, stressHandIns(), sent.Round(time.Millisecond), (cleared - sent).Round(time.Millisecond))

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

// answered waits for the plugin's answers and says when the last of them landed, which is the queue clearing.
func stressAnswers(t *testing.T, data string, wanted int, within time.Duration) ([]map[string]any, time.Duration) {
	t.Helper()

	queue, _ := Queue4In(data)
	began := time.Now()

	for until := began.Add(within); time.Now().Before(until); time.Sleep(200 * time.Millisecond) {
		files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
		if len(files) >= wanted {
			return drained(t, data), time.Since(began)
		}
	}

	return drained(t, data), time.Since(began)
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

// sinful is one file of the project: a class of the shape a real one has, with sins real rules find, so what the
// rules cost here is what they cost on a consumer's tree.
func stressFile(at int) string {
	return fmt.Sprintf(`from shop.base import Base


class Thing%d(Base):
    def __init__(self, name: str = "", total: int = 0):
        self.name = name
        self.total = total

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
`, at)
}

// _ keeps warm imported for the socket the service registers, which a reader of this file looks for.
var _ = warm.Socket
