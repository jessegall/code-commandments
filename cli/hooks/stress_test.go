package hooks

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"math/rand"
	"regexp"
	"slices"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"sync/atomic"
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
		at          time.Time
		edit, other time.Duration
	}

	for worker := 1; worker <= *stressWorkers; worker++ {
		work.Add(1)

		go func(worker int) {
			defer work.Done()

			for edit := 0; edit < *stressEdits; edit++ {
				if edit > 0 {
					time.Sleep(*stressApart)
				}

				took := sendEvent(t, service, stressEvent(worker, edit, stressEdit(t, root, worker, edit)))

				last.Lock()
				last.at = time.Now()
				if took > last.edit {
					last.edit = took
				}
				last.Unlock()


			}

			// The events a session raises beside its edits: a heartbeat every minute for every open agent, and
			// one when the agent stops. Once per worker, which is what a session raises, and after its edits,
			// where a stop really falls.
			for _, event := range []string{"ticked", "idle"} {
				waited := sendEvent(t, service, stressMoment(worker, event))

				last.Lock()
				if waited > last.other {
					last.other = waited
				}
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
	t.Logf("the journal waited %v for an edit's answer at worst, and %v for a ticked or idle one",
		last.edit.Round(time.Millisecond), last.other.Round(time.Millisecond))

	t.Logf("%d edits %v apart and %d hand-ins were sent in %v; the last answer landed %v after the last edit",
		*stressWorkers**stressEdits, *stressApart, stressHandIns(), time.Since(fired).Round(time.Millisecond),
		landed.Sub(last.at).Round(time.Millisecond))

	stressReport(t, edits(answers))
	stressTold(t, edits(answers))
	stressStop(t, root, data)
}

// stressStop says where the time in a stop's answer goes. The journal waits on that answer and raises one every
// minute for every open agent, so it is the plugin's largest debt to it; the sweep that re-reads every marked
// file is the obvious half, and this says how much of it the sweep really is.
func stressStop(t *testing.T, root, data string) {
	t.Helper()

	line, known := Moment4Of(stressMoment(1, "idle"))
	if !known {
		t.Fatal("the journal's idle event is no moment")
	}

	swept := time.Now()
	marks, files := sweptMarks(AnnouncedIn(data, MomentOf(line)), root)
	t.Logf("the sweep read %d marked file(s) in %v and found %d mark(s)", len(files), time.Since(swept).Round(time.Millisecond), len(marks))

	settling := time.Now()
	settlement := settled(MomentOf(line), root, nil, nil)
	t.Logf("the settlement took %v, the sweep inside it, and settles %d", time.Since(settling).Round(time.Millisecond), len(settlement.Settles()))

	whole := time.Now()
	answerWith(Stamped(line), every, true)
	t.Logf("the whole answer to a stop took %v", time.Since(whole).Round(time.Millisecond))
}

// edits are the answers about the files this run edited. A session raises ticked and idle beside its edits, and
// those are answered too, so the queue holds more than the edits: an answer about a heartbeat has nothing to say
// about a file and must not be read as an edit that was answered with nothing.
func edits(answers []map[string]any) []map[string]any {
	var mine []map[string]any
	for _, answer := range answers {
		if strings.Contains(asText(answer["tell"]), "thing") {
			mine = append(mine, answer)
		}
	}

	return mine
}

// stressTold holds what the run was TOLD, not only how long it took. A harness that reads durations alone is a
// load generator: a plugin wrong about every file but the one it reports would print the same figures, and that
// is how two wrong answers reached a consumer before the harness could find them.
//
// Each answer must name the sin the edit wrote inside its own file, and the sin whose chain of parents crosses
// three files. The second is the one worth having: it cannot be answered from the edited file at all, so an
// answer carrying it proves the edit was judged against the whole program.
func stressTold(t *testing.T, answers []map[string]any) {
	t.Helper()

	for at, answer := range answers {
		told := asText(answer["tell"])

		for _, sin := range []string{"deep-python-nesting", "thing-by-its-kind"} {
			if !strings.Contains(told, sin) {
				t.Errorf("answer %d names no %s, so the edit was not judged against what it should be:\n%s", at+1, sin, told)
			}
		}
	}
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

// sendEvent sends one of the journal's events on the plugin's socket and reads the answer, which is exactly what
// the journal does, and says how long that round trip took. The journal WAITS on it, so it is the one number the
// plugin owes above all others: a judging that runs before the answer goes back is a journal held up.
func sendEvent(t *testing.T, socket string, event map[string]any) time.Duration {
	t.Helper()

	began := time.Now()

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

	return time.Since(began)
}

// stressMoment is one of the journal's other events, which the plugin answers IN FULL on the connection rather
// than through the queue. A session raises them throughout: ticked every minute per open agent, idle whenever an
// agent stops. The journal waits on each answer, so what they cost is a journal held up.
func stressMoment(worker int, event string) map[string]any {
	return map[string]any{
		"v": 1, "event": event, "session": fmt.Sprintf("s%d", worker), "environment": "main", "agent": worker,
		"data": map[string]any{},
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

class Deepened%d(RebuildKind):
    number = %d

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
	appended := fmt.Sprintf(stressAppended, worker*1000+edit, edit)

	if err := os.WriteFile(path, []byte(was+appended), 0o644); err != nil {
		t.Fatal(err)
	}

	var added []any
	from := len(strings.Split(strings.TrimRight(was, "\n"), "\n"))

	for at, text := range strings.Split(strings.TrimRight(appended, "\n"), "\n") {
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

	var out, errs bytes.Buffer
	code, err := (judge.Command{In: worktree}).Run(cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=2"),
		cli.Console{Out: &out, Err: &errs})

	t.Logf("worker %d handed in: exit %d, err %v, in %v", worker, code, err, time.Since(began).Round(time.Millisecond))

	// A hand-in judges what the worker changed, and the worker changed files with sins in them, so an exit of
	// anything else is a judging that found nothing where there is something to find.
	if err != nil || code != 1 {
		t.Errorf("worker %d handed in with exit %d and %v; its own changes hold sins:\n%s%s", worker, code, err, out.String(), errs.String())
	}

	for _, sin := range []string{"DeepNestingDetector", "ThingByItsKindDetector"} {
		if !strings.Contains(out.String(), sin) {
			t.Errorf("worker %d's hand-in names no %s:\n%s%s", worker, sin, out.String(), errs.String())
		}
	}
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

	var held []map[string]any

	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(100 * time.Millisecond) {
		files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
		if len(files) == 0 {
			continue
		}

		// Read as they land, since an answer about a heartbeat lands among them and the count that matters is
		// the edits'.
		held = append(held, drained(t, data)...)
		if len(edits(held)) >= wanted {
			return held, time.Now()
		}
	}

	return append(held, drained(t, data)...), time.Now()

}

// stressProject is a project of the size asked for, with a worktree per worker as the journal cuts them, and the
// plugin's data folder. Every rule the project's languages have judges it, as a consumer's run is judged.
func stressProject(t *testing.T, files, workers int) (root, data string) {
	t.Helper()

	data = t.TempDir()
	t.Setenv(pluginData, data)

	root = stressTreeAt(t, files, workers)
	t.Setenv("JOURNAL_ROOT", root)

	return root, data
}

// stressTreeAt builds one project of that size with that many worktrees cut inside it, and touches no
// environment: several of them stand at once in the many-trees run, and only the moment says which is whose.
func stressTreeAt(t *testing.T, files, workers int) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	written := map[string]string{
		// Every shipped rule judges it, as a consumer's run is judged, and one rule of the project's own
		// beside them: naming a rule here turns a custom one ON and takes none of the shipped ones off.
		".commandments/config.json": `{"paths": ["shop"], "detectors": ["ThingByItsKindDetector"]}`,

		// A rule that cannot be answered from one file: the chain from Deepened through RebuildKind to Kind
		// crosses three files, so an answer that names this sin proves the edit was judged against the whole
		// program and not against its own file.
		".commandments/custom/ThingByItsKindDetector.json": `{"engine": "python",
 "sin": {"name": "thing-by-its-kind", "description": "a class the chain of its parents says is a kind", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"extendsAny": "Kind"}]}}`,

		"shop/__init__.py": "",
		"shop/base.py":     "class Base:\n    number = 0\n",
		"shop/kinds.py":    "class Kind:\n    number = 0\n",
		"shop/rebuild.py": `from shop.kinds import Kind


class RebuildKind(Kind):
    statements: list[str] = []
`,
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

	return root
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
from shop.rebuild import RebuildKind
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

// The many-trees run is the harness doing the looking. Three projects, each with a tree of its own in memory and
// three worktrees cut inside it: twelve checkouts, every one of them writing at the same time and not politely.
// Each picks its next move at random — write a file, create several in a row, edit one, cut lines out of one,
// delete one — and goes on picking.
//
//	scripts/dev go test ./cli/hooks/ -run ManyTreesAtOnce -v -timeout 30m -args -stress -stress.many
var (
	stressMany      = flag.Bool("stress.many", false, "run the many-trees run instead of the load")
	stressRoots     = flag.Int("stress.roots", 3, "how many projects, each with a tree of its own")
	stressWorktrees = flag.Int("stress.worktrees", 3, "how many worktrees are cut inside each project")
	stressMoves     = flag.Int("stress.moves", 1000, "how many moves each checkout makes")
	stressSeed      = flag.Int64("stress.seed", 0, "the seed the moves are drawn from; 0 draws one and prints it")
)

// TestManyTreesAtOnce holds what must be true however the twelve go about it. The assertions are the point, and
// each of them is a wrong answer the plugin has really given:
//
// No answer names a file that is not on disk. That is the fault a consumer reported — three sins in a file
// deleted a minute earlier — and this would have found it before they ever saw it.
//
// No answer names a file of another checkout. Twelve trees in memory at once is where that breaks, and nothing
// here had ever run more than three.
//
// Every edit of a file that holds a sin is answered about that file, so going ham cannot make the plugin quiet.
func TestManyTreesAtOnce(t *testing.T) {
	if !*stress || !*stressMany {
		t.Skip("the many-trees run goes on request: -args -stress -stress.many")
	}

	seed := *stressSeed
	if seed == 0 {
		seed = time.Now().UnixNano()
	}
	t.Logf("the moves are drawn from seed %d; pass -stress.seed=%d to make this run again", seed, seed)

	data := t.TempDir()
	t.Setenv(pluginData, data)

	Serves()
	t.Cleanup(func() { serving = false })

	checkouts := stressCheckouts(t, seed)
	t.Logf("%d checkouts of one project, each making %d moves", len(checkouts), *stressMoves)

	var work sync.WaitGroup
	for _, checkout := range checkouts {
		work.Add(1)

		go func(checkout *stressCheckout) {
			defer work.Done()

			for move := 0; move < *stressMoves; move++ {
				checkout.move(t, move)
			}
		}(checkout)
	}

	work.Wait()

	t.Logf("%d answers were held to the three invariants as they came", answered.Load())
}

// answered is how many answers the run has held to its invariants, which is what says the run proved anything at
// all: a fuzz that asserts nothing until the end proves only that nothing crashed.
var answered atomic.Int64

// judging is held while one move is made, answered and checked. The plugin judges ONE moment at a time, so this
// is the plugin's own shape rather than a weakening of the run: it is also what makes every answer attributable,
// since the queue is one and a drain takes whatever is in it.
var oneAtATime sync.Mutex

// stressCheckout is one checkout going ham: a project's own or a worktree of it, with its own files and its own
// moves to draw.
type stressCheckout struct {
	// At is the folder the checkout stands in, Project the main checkout it belongs to, and Named its number,
	// which every class it writes carries so an answer about its files can be told from an answer about
	// another's.
	At, Project string
	Named       int

	draw  *rand.Rand
	mine  []string
	wrote int
	at    int
}

// stressCheckouts are the project's own checkout and every worktree cut inside it. One project, because the
// journal runs a service for ONE and names it in JOURNAL_ROOT, and a moment carries no project of its own: the
// several trees come from the BRANCHES the main checkout stands on, which is how a base is keyed and what a main
// agent switching branch really does to one.
func stressCheckouts(t *testing.T, seed int64) []*stressCheckout {
	t.Helper()

	workers := *stressRoots * *stressWorktrees
	project := stressTreeAt(t, 40, workers)
	t.Setenv("JOURNAL_ROOT", project)

	settings, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}

	// The tree, read before anything writes, as a session has it read.
	Warming(project, settings)

	folders := []string{project}
	for worker := 1; worker <= workers; worker++ {
		folders = append(folders, filepath.Join(project, ".journal", "worktrees", fmt.Sprintf("worker-%d", worker)))
	}

	var checkouts []*stressCheckout
	for named, folder := range folders {
		checkouts = append(checkouts, &stressCheckout{
			At:      folder,
			Project: project,
			Named:   named + 1,
			draw:    rand.New(rand.NewSource(seed + int64(named))),
			mine:    []string{filepath.Join(folder, "shop", "thing0.py")},
		})
	}

	if !heldWithin(git.ProjectRoot(project), git.CurrentBranch(project), 5*time.Minute) {
		t.Fatalf("no tree was held for %s", project)
	}

	return checkouts
}

// move makes one move of the checkout's own choosing and tells the plugin about it, as the journal would. The
// git moves are drawn rarely, as they happen rarely, and they are the ones that move the ground under a tree
// held in memory: a base is keyed by the project and the branch its MAIN checkout stands on, so one agent
// switching branch changes which tree every other checkout answers from.
func (c *stressCheckout) move(t *testing.T, move int) {
	c.at = move

	switch c.draw.Intn(20) {
	case 0, 1, 2, 3:
		c.writes(t, 1)
	case 4:
		c.writes(t, 3)
	case 5, 6, 7, 8, 9, 10:
		c.edits(t)
	case 11, 12:
		c.cuts(t)
	case 13, 14, 15:
		c.deletes(t)
	case 16, 17:
		c.commits(t)
	case 18:
		c.switches(t)
	default:
		c.recuts(t)
	}
}

// commits puts what it has written into its own branch, which is what an agent does at the end of a stretch of
// work and what moves a checkout's HEAD under the tree.
func (c *stressCheckout) commits(t *testing.T) {
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"})
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "work"})
}

// switches puts the MAIN checkout on another branch, which only the main one can do and which is the move that
// worries us most: every tree is keyed by the branch the main checkout stands on, so after this every worktree
// answers from a branch nobody is on any more.
func (c *stressCheckout) switches(t *testing.T) {
	if c.At != c.Project {
		c.edits(t)

		return
	}

	c.commits(t)
	c.runs(t, []string{"checkout", "-q", "-B", fmt.Sprintf("main-%d", c.draw.Intn(3))})
}

// recuts drops this worktree and cuts it again on a branch of its own, in the same folder, which is what the
// journal does when an agent's work is taken in and another agent is sent out.
func (c *stressCheckout) recuts(t *testing.T) {
	if c.At == c.Project {
		c.commits(t)

		return
	}

	c.commits(t)

	if out, err := exec.Command("git", "-C", c.Project, "worktree", "remove", "--force", c.At).CombinedOutput(); err != nil {
		t.Logf("worktree %s was not removed: %s", c.At, out)

		return
	}

	cut := []string{"worktree", "add", "-q", "-b", fmt.Sprintf("worker-%d-%d", c.Named, c.draw.Intn(1000)), c.At}
	if out, err := exec.Command("git", append([]string{"-C", c.Project}, cut...)...).CombinedOutput(); err != nil {
		t.Fatalf("the worktree %s could not be cut again: %s", c.At, out)
	}

	// Its own files are gone with the worktree, and what it holds now is the branch's own.
	c.mine = []string{filepath.Join(c.At, "shop", "thing0.py")}
}

// runs a git command in this checkout, saying what it said rather than failing: a fuzz run makes moves that git
// refuses — a commit with nothing to commit, a branch that is checked out elsewhere — and those are moves an
// agent makes too.
func (c *stressCheckout) runs(t *testing.T, args []string) {
	if out, err := exec.Command("git", append([]string{"-C", c.At}, args...)...).CombinedOutput(); err != nil {
		t.Logf("git %v in %s: %s", args, filepath.Base(c.At), out)
	}
}

// writes creates that many files in a row, each with a sin of its own, and tells the plugin about each.
func (c *stressCheckout) writes(t *testing.T, many int) {
	for at := 0; at < many; at++ {
		c.wrote++
		file := filepath.Join(c.At, "shop", fmt.Sprintf("made%d_%d.py", c.Named, c.wrote))

		written := stressMine(c.Named)
		if err := os.WriteFile(file, []byte(written), 0o644); err != nil {
			t.Fatal(err)
		}

		c.mine = append(c.mine, file)
		c.tells(t, file, 1, lines(written))
	}
}

// edits appends a sin to one of its files, which is the ordinary move.
func (c *stressCheckout) edits(t *testing.T) {
	file := c.one()
	if file == "" {
		c.writes(t, 1)

		return
	}

	was, err := os.ReadFile(file)
	if err != nil {
		c.writes(t, 1)

		return
	}

	appended := stressMine(c.Named)
	if err := os.WriteFile(file, append(was, []byte(appended)...), 0o644); err != nil {
		t.Fatal(err)
	}

	// The lines it really added, which is what the journal carries and what decides whether a sin is THIS
	// edit's doing: named wrongly, every sin reads as somebody else's and the answer is empty.
	c.tells(t, file, lines(string(was)), lines(string(was))+lines(appended))
}

// cuts takes the lines of one of its files away again, so a sin it was told about is gone.
func (c *stressCheckout) cuts(t *testing.T) {
	file := c.one()
	if file == "" {
		return
	}

	was, err := os.ReadFile(file)
	if err != nil {
		return
	}

	held := strings.Split(string(was), "\n")
	if len(held) < 4 {
		return
	}

	kept := held[:len(held)/2]
	if err := os.WriteFile(file, []byte(strings.Join(kept, "\n")+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	// A cut adds nothing, so nothing in the file is this edit's doing and no answer is owed for it. It is sent
	// all the same: what it must not do is raise a sin as newly written, or name a line that is gone.
	c.tells(t, file, 0, 0)
}

// deletes removes one of its files the way a shell does, with no event of its own: nothing announces a removal,
// and the tree learns of it only by looking.
func (c *stressCheckout) deletes(t *testing.T) {
	file := c.one()
	if file == "" {
		return
	}

	if err := os.Remove(file); err != nil {
		return
	}

	c.mine = slices.DeleteFunc(c.mine, func(path string) bool { return path == file })
}

// under is where this checkout's own files lie, as a path under the project, which is how the journal names them
// and how an answer's paths read.
func (c *stressCheckout) under() string {
	if c.At == c.Project {
		return "shop/"
	}

	at, err := filepath.Rel(c.Project, c.At)
	if err != nil {
		return ""
	}

	return at + "/"
}

// one is a file of this checkout's own, drawn at random; empty where it has none left.
func (c *stressCheckout) one() string {
	if len(c.mine) == 0 {
		return ""
	}

	return c.mine[c.draw.Intn(len(c.mine))]
}

// tells the plugin what it just did, as the journal tells it: the path relative to the checkout, since that is
// the shape the journal sends and the shape that has to be resolved against the right root.
func (c *stressCheckout) tells(t *testing.T, file string, from, to int) {
	// Relative to the PROJECT, which is how the journal names a file: a worktree's own file arrives as
	// .journal/worktrees/worker-1/shop/thing0.py, not as shop/thing0.py, and named from the checkout instead it
	// is looked for under the project, where it does not exist, and the moment is dropped without a word.
	under, err := filepath.Rel(c.Project, file)
	if err != nil {
		t.Fatal(err)
	}

	var added []any
	for line := from; line <= to; line++ {
		added = append(added, map[string]any{"line_type": "add", "line": line, "text": "class Mine:"})
	}

	oneAtATime.Lock()
	defer oneAtATime.Unlock()

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": fmt.Sprintf("s%d", c.Named), "environment": "main",
		"agent": c.Named, "cwd": c.At,
		"data": map[string]any{
			"path": under, "kind": "edit", "added": len(added), "removed": 0, "tool_use": c.wrote + 1,
			"lines": added,
		},
	}, os.Getenv(pluginData))

	stressHeld(t, c, c.at, drained(t, os.Getenv(pluginData)))
}

// lines is how many lines the text holds.
func lines(text string) int {
	return len(strings.Split(strings.TrimRight(text, "\n"), "\n"))
}

// stressMine is a class of this checkout's own, carrying sins of its own and named after the checkout, so an
// answer about one checkout's files can be told from an answer about another's.
func stressMine(named int) string {
	return fmt.Sprintf(`from shop.rebuild import RebuildKind

class Mine%dAt%d(RebuildKind):
    number = %d

    def deepened(self, order, rate=None):
        if order is not None:
            for line in order.lines:
                if line.total > 0:
                    if rate is not None:
                        return line.total * rate
                    else:
                        return line.total
        return 0
`, named, time.Now().UnixNano()%100000, named)
}

// told are the paths a tell names.
var toldPaths = regexp.MustCompile(`(?:^|\s)([\w./-]+\.py):(\d+)`)

// stressHeld holds what the plugin just answered to what must be true of it, whatever move was made. Checked
// here and not at the end of the run: a file deleted later would make a right answer look wrong, and an answer
// checked a thousand moves after it was given names nothing anybody can act on.
func stressHeld(t *testing.T, mine *stressCheckout, move int, answers []map[string]any) {
	t.Helper()

	for _, answer := range answers {
		told := asText(answer["tell"])

		// Whose answer this is. The journal hands a queued answer to the agent it NAMES, so an answer about one
		// checkout's file written for another agent is one agent told about another's work, which happened once
		// before a moment carried its agent at all.
		whose := asAgent(answer["agent"])

		for _, named := range toldPaths.FindAllStringSubmatch(told, -1) {
			under := named[1]
			answered.Add(1)

			// A path a tell names is relative to the project, and it must be a file that is really there: an
			// answer about a file nobody has any more is the worst answer the plugin can give. This is the
			// fault a consumer reported, three sins in a file deleted a minute earlier.
			if info, err := os.Stat(filepath.Join(mine.Project, under)); err != nil || !info.Mode().IsRegular() {
				t.Fatalf("move %d of checkout %d: an answer names %s, which is not a file: %v\n%s",
					move, mine.Named, under, err, told)
			}

			// And it is a file of the checkout the answer is FOR. Twelve trees in memory at once is where that
			// goes wrong: a worktree's file spliced into another checkout's program, or a base keyed by a
			// branch nobody is on, would show up here as one agent told about another's file.
			if whose == mine.Named && !strings.HasPrefix(under, mine.under()) {
				t.Fatalf("move %d of checkout %d: it is told about %s, which is not its own (it owns %q)\n%s",
					move, mine.Named, under, mine.under(), told)
			}
		}
	}
}
