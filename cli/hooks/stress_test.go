package hooks

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"io/fs"
	"math/rand"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"slices"
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
	"github.com/jessegall/code-commandments/cli/warm"
)

var (
	stress        = flag.Bool("stress", false, "run the stress harness rather than skipping it")
	stressFiles   = flag.Int("stress.files", 400, "how many source files the project it builds holds")
	stressWorkers = flag.Int("stress.workers", 3, "how many workers edit and hand in at once")
	stressEdits   = flag.Int("stress.edits", 3, "how many files each worker edits")
	stressHandIn  = flag.Bool("stress.hand-in", true, "run each worker's hand-in judging in the same window")
	stressWithin  = flag.Duration("stress.within", 10*time.Second,
		"how long after the last edit the last answer may land before the run fails")
	stressAsks  = flag.Bool("stress.asks", false, "time one ask of 1, 2, 3 and 5 files through the warm bridge instead of the load")
	stressAhead = flag.Bool("stress.ahead", true, "parse each edited file when its event arrives, together with every file waiting then")
	stressApart = flag.Duration("stress.apart", 0, "how long a worker waits between its edits; a real agent edits seconds apart, not all at once")
)

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

	warming := time.Now()
	Warming(root, settings)

	if !heldWithin(project, branch, 15*time.Minute) {
		t.Fatalf("no base was held after %v", time.Since(warming).Round(time.Second))
	}
	t.Cleanup(func() { kept.Drop(project, branch) })

	read := time.Since(warming)
	t.Logf("the base of %d files was read in %v", *stressFiles, read.Round(time.Millisecond))

	drained(t, data)

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

				took := sendEvent(t, service, stressEvent(root, worker, edit, stressEdit(t, root, worker, edit)))

				last.Lock()
				last.at = time.Now()
				if took > last.edit {
					last.edit = took
				}
				last.Unlock()

			}

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

	answers, landed := stressAnswers(t, data, stressEditedFiles(), *stressWithin)

	if waited := stressWorstWait(edits(answers)); waited > *stressWithin {
		t.Errorf("an edit waited %v from the journal handing it over to its answer, past the %v an agent may wait "+
			"to hear about the file it just wrote", waited.Round(time.Millisecond), *stressWithin)
	}

	_ = landed

	t.Logf("the journal waited %v for an edit's answer at worst, and %v for a ticked or idle one",
		last.edit.Round(time.Millisecond), last.other.Round(time.Millisecond))

	t.Logf("%d edits %v apart and %d hand-ins were sent in %v; the last answer landed %v after the last edit",
		*stressWorkers**stressEdits, *stressApart, stressHandIns(), time.Since(fired).Round(time.Millisecond),
		landed.Sub(last.at).Round(time.Millisecond))

	stressReport(t, edits(answers))
	stressTold(t, edits(answers))
	stressStop(t, root, data)
}

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
	settlement := settled(MomentOf(line), root, nil, nil, nil)
	t.Logf("the settlement took %v, the sweep inside it, and settles %d", time.Since(settling).Round(time.Millisecond), len(settlement.Settles()))

	whole := time.Now()
	answerWith(Stamped(line), every, true)
	t.Logf("the whole answer to a stop took %v", time.Since(whole).Round(time.Millisecond))
}

func edits(answers []map[string]any) []map[string]any {
	var mine []map[string]any
	for _, answer := range answers {
		if strings.Contains(asText(answer["tell"]), "thing") {
			mine = append(mine, answer)
		}
	}

	return mine
}

func stressTold(t *testing.T, answers []map[string]any) {
	t.Helper()

	var said strings.Builder
	for _, answer := range answers {
		said.WriteString(asText(answer["tell"]))
		said.WriteString("\n")
	}

	for _, sin := range []string{"deep-python-nesting", "thing-by-its-kind"} {
		if !strings.Contains(said.String(), sin) {
			t.Errorf("nothing the run was told names %s, so the edits were not judged against what they should "+
				"be:\n%s", sin, said.String())
		}
	}
}

func stressHandIns() int {
	if !*stressHandIn {
		return 0
	}

	return *stressWorkers
}

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

func said(answer map[string]any, named string) int64 {
	said, _ := answer[named].(float64)

	return int64(said)
}

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

func serviceAt(t *testing.T, root string) string {
	t.Helper()

	socket := fmt.Sprintf("/tmp/cc-stress-%d.sock", os.Getpid())
	t.Setenv(pluginSocket, socket)
	t.Cleanup(func() { os.Remove(socket) })

	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.Chdir(was) })

	t.Cleanup(func() { serving.Store(false) })

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

func stressMoment(worker int, event string) map[string]any {
	return map[string]any{
		"v": 1, "event": event, "session": fmt.Sprintf("s%d", worker), "environment": "main", "agent": worker,
		"data": map[string]any{},
	}
}

func stressEvent(root string, worker, edit int, added []any) map[string]any {
	file := filepath.Join(stressWorktreeIn(root, worker), "shop", "goods", fmt.Sprintf("thing%d.py", edit))
	named := file
	if under, inside := warm.Under(root, file); inside {
		named = under
	}

	return map[string]any{
		"v": 1, "event": "file_edited", "session": fmt.Sprintf("s%d", worker), "environment": stressEnvironment,
		"agent": worker,
		"data": map[string]any{
			"path":  named,
			"kind":  "edit",
			"added": len(added), "removed": 0, "tool_use": edit + 1,
			"lines": added,
		},
	}
}

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

func stressEdit(t *testing.T, root string, worker, edit int) []any {
	t.Helper()

	path := filepath.Join(stressWorktreeIn(root, worker), "shop", "goods", fmt.Sprintf("thing%d.py", edit))

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

func stressHandInBy(t *testing.T, root string, worker int) {
	t.Helper()

	worktree := stressWorktreeIn(root, worker)

	stressServed(t, root, worktree, worker)

	began := time.Now()

	var out, errs bytes.Buffer
	code, err := (judge.Command{In: worktree}).Run(cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=2"),
		cli.Console{Out: &out, Err: &errs})

	t.Logf("worker %d handed in: exit %d, err %v, in %v", worker, code, err, time.Since(began).Round(time.Millisecond))

	if err != nil || code != 1 {
		t.Errorf("worker %d handed in with exit %d and %v; its own changes hold sins:\n%s%s", worker, code, err, out.String(), errs.String())
	}

	for _, sin := range []string{"DeepNestingDetector", "ThingByItsKindDetector"} {
		if !strings.Contains(out.String(), sin) {
			t.Errorf("worker %d's hand-in names no %s:\n%s%s", worker, sin, out.String(), errs.String())
		}
	}
}

func stressServed(t *testing.T, root, in string, worker int) {
	t.Helper()

	answer, asked := warm.Asked(root, warm.Request{
		Judge: []string{"--changes", "--no-checklist", "--parallel=2"},
		In:    in,
	})

	if !asked {
		t.Errorf("worker %d's hand-in was DECLINED, so it reads the whole tree in its own process: %s",
			worker, answer.Because)

		return
	}

	for _, sin := range []string{"DeepNestingDetector", "ThingByItsKindDetector"} {
		if !strings.Contains(answer.Out, sin) {
			t.Errorf("worker %d's served hand-in names no %s:\n%s%s", worker, sin, answer.Out, answer.Err)
		}
	}
}

func heldWithin(project, branch string, within time.Duration) bool {
	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(200 * time.Millisecond) {
		if _, held := kept.Holds(project, branch); held {
			return true
		}
	}

	return false
}

func stressAnswers(t *testing.T, data string, wanted []string, within time.Duration) ([]map[string]any, time.Time) {
	t.Helper()

	queue, _ := Queue4In(data)

	var held []map[string]any
	landed := time.Now()

	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(100 * time.Millisecond) {
		files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
		if len(files) > 0 {
			held = append(held, drained(t, data)...)
			landed = time.Now()
		}

		if stressNamedAll(held, wanted) {
			return held, landed
		}
	}

	return append(held, drained(t, data)...), time.Now()
}

func stressNamedAll(answers []map[string]any, wanted []string) bool {
	said := map[string]bool{}
	for _, answer := range answers {
		whose := int(asFloat(answer["agent"]))
		for _, named := range stressReported(answer) {
			said[fmt.Sprintf("%d\x00%s", whose, filepath.Base(named))] = true
		}
	}

	for _, file := range wanted {
		if !said[file] {
			return false
		}
	}

	return true
}

func stressProject(t *testing.T, files, workers int) (root, data string) {
	t.Helper()

	data = t.TempDir()
	t.Setenv(pluginData, data)

	root = stressTreeAt(t, files, workers)
	t.Setenv("JOURNAL_ROOT", root)

	return root, data
}

func stressTreeAt(t *testing.T, files, workers int) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	written := map[string]string{

		".commandments/config.json": `{"paths": ["shop/goods", "shop/tills", "tools/hook"],
 "detectors": ["ThingByItsKindDetector"]}`,

		".commandments/custom/ThingByItsKindDetector.json": `{"engine": "python",
 "sin": {"name": "thing-by-its-kind", "description": "a class the chain of its parents says is a kind", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"extendsAny": "Kind"}]}}`,

		"shop/__init__.py":       "",
		"tools/__init__.py":      "",
		"shop/goods/__init__.py": "",
		"shop/goods/base.py":     "class Base:\n    number = 0\n",
		"shop/goods/kinds.py":    "class Kind:\n    number = 0\n",
		"shop/goods/rebuild.py": `from shop.goods.kinds import Kind


class RebuildKind(Kind):
    statements: list[str] = []
`,
		"shop/tills/__init__.py": "",
		"shop/tills/till.py":     "class Till:\n    number = 0\n",
		"tools/hook/__init__.py": "",
		"tools/hook/hook.py":     "class Hook:\n    number = 0\n",
	}
	for at := 0; at < files; at++ {
		written[fmt.Sprintf("shop/goods/thing%d.py", at)] = stressFile(at)
	}

	for at := 0; at <= leastReadInOneGo; at++ {
		written[fmt.Sprintf("shop/goods/Thing%d.cs", at)] = fmt.Sprintf("namespace Shop;\n\npublic class Thing%d {}\n", at)
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
		commands = append(commands, []string{"worktree", "add", "-q", "-b", stressBranchOf(worker),
			stressWorktreeIn(root, worker)})
	}

	for _, command := range commands {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	return root
}

const stressEnvironment = "development"

func stressWorktreeIn(project string, worker int) string {
	if worker%2 == 1 {
		return filepath.Join(project, ".journal", "worktrees", stressEnvironment, fmt.Sprintf("worker-%d", worker))
	}

	return filepath.Join(filepath.Dir(project), filepath.Base(project)+fmt.Sprintf("-worker-%d", worker))
}

func stressBranchOf(worker int) string {
	if worker%2 == 1 {
		return fmt.Sprintf("journal/%s/worker-%d", stressEnvironment, worker)
	}

	return fmt.Sprintf("feature/worker-%d", worker)
}

func stressFile(at int) string {
	imports, uses := "", ""

	for back := 1; back <= 3 && at-back >= 0; back++ {
		imports += fmt.Sprintf("from shop.goods.thing%d import Thing%d\n", at-back, at-back)
		uses += fmt.Sprintf("        self.near%d = Thing%d(name)\n", at-back, at-back)
	}

	return fmt.Sprintf(`from shop.goods.base import Base
from shop.goods.rebuild import RebuildKind
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

func TestWhatAnAskCostsByItsFiles(t *testing.T) {
	if !*stress || !*stressAsks {
		t.Skip("the ask measurement runs on request: -args -stress -stress.asks")
	}

	root, _ := stressProject(t, *stressFiles, 1)
	project := git.ProjectRoot(root)
	stressBridge(t, project)

	if _, err := scan.Walk(filesOf(t, root, 1), source.Excluded{}).Load(); err != nil {
		t.Fatalf("the python bridge cannot answer here: %v", err)
	}

	for _, many := range []int{1, 2, 3, 5, 9, 25, 50, 100, 200, 400, 800, 1} {
		asked := filesOf(t, root, many)

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

func filesOf(t *testing.T, root string, many int) []string {
	t.Helper()

	var asked []string
	for at := 0; at < many; at++ {
		asked = append(asked, filepath.Join(root, "shop", "goods", fmt.Sprintf("thing%d.py", at)))
	}

	return asked
}

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

var (
	stressMany      = flag.Bool("stress.many", false, "run the many-trees run instead of the load")
	stressRoots     = flag.Int("stress.roots", 3, "how many projects, each with a tree of its own")
	stressWorktrees = flag.Int("stress.worktrees", 3, "how many worktrees are cut inside each project")
	stressMoves     = flag.Int("stress.moves", 1000, "how many moves each checkout makes")
	stressSeed      = flag.Int64("stress.seed", 0, "the seed the moves are drawn from; 0 draws one and prints it")
)

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
	t.Cleanup(func() { serving.Store(false) })

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

	if answered.Load() == 0 {
		t.Fatal("not one answer came back from the whole run, and every move was held to nothing")
	}

	t.Logf("%d answers were held to the invariants as they came, %d checkouts at once at the most", answered.Load(), atOnce.Load())

	if *stressWorktrees > 1 && atOnce.Load() < 2 {
		t.Errorf("no two checkouts were ever in the service at the same moment, so nothing this run claims about "+
			"several at once was really tried: %d at the most", atOnce.Load())
	}
}

var answered atomic.Int64

var inFlight, atOnce atomic.Int64

func flying() func() {
	if now := inFlight.Add(1); now > atOnce.Load() {
		atOnce.Store(now)
	}

	return func() { inFlight.Add(-1) }
}

// stressCheckout is one checkout going ham: a project's own or a worktree of it, with its own files and its own moves to draw.
type stressCheckout struct {
	own sync.Mutex

	At, Project string
	Named       int

	Service string

	Told string

	draw  *rand.Rand
	mine  []string
	wrote int
	at    int
}

func stressCheckouts(t *testing.T, seed int64) []*stressCheckout {
	t.Helper()

	workers := *stressRoots * *stressWorktrees
	built := stressTreeAt(t, 40, workers)

	linked := filepath.Join(t.TempDir(), "project")
	if err := os.Symlink(built, linked); err != nil {
		t.Logf("this filesystem has no links, so the run is made on the resolved path: %v", err)

		linked = built
	}

	project := built
	t.Setenv("JOURNAL_ROOT", linked)

	settings, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}

	service := serviceAt(t, project)

	Warming(project, settings)

	folders := []string{project}
	for worker := 1; worker <= workers; worker++ {
		folders = append(folders, stressWorktreeIn(project, worker))
	}

	var checkouts []*stressCheckout
	for named, folder := range folders {
		checkout := &stressCheckout{
			At:      folder,
			Project: project,
			Told:    strings.Replace(folder, project, linked, 1),
			Service: service,
			Named:   named + 1,
			draw:    rand.New(rand.NewSource(seed + int64(named))),
			mine:    []string{filepath.Join(folder, "shop", "goods", "thing0.py")},
		}

		stressRunning[checkout.Named] = checkout
		checkouts = append(checkouts, checkout)
	}

	if !heldWithin(git.ProjectRoot(project), git.CurrentBranch(project), 5*time.Minute) {
		t.Fatalf("no tree was held for %s", project)
	}

	return checkouts
}

func (c *stressCheckout) move(t *testing.T, move int) {
	c.at = move

	branch, held := c.holding()

	switch c.draw.Intn(29) {
	case 0, 1, 2, 3:
		c.writes(t, 1)
	case 4:
		c.writes(t, 3)
	case 5, 6, 7, 8, 9:
		c.edits(t)
	case 10, 11:
		c.cuts(t)
	case 12, 13:
		c.deletes(t)
	case 14:
		c.renames(t)
	case 19:
		c.writesOddly(t)
	case 15:
		c.forgets(t)

		return
	case 26:
		c.abandons(t)
	case 27:
		c.handsIn(t)

		return
	case 20, 21, 22, 23, 24, 25:
		c.raises(t)
	case 16, 17:
		c.commits(t)

		return
	case 18:
		c.switches(t)

		return
	default:
		c.recuts(t)

		return
	}

	if now, holds := c.holding(); held && now == branch && !holds {
		t.Fatalf("move %d of checkout %d: writing in its own checkout cost the project its tree on branch %s, so "+
			"every edit behind it is judged on its own file alone until the tree is read again", move, c.Named, branch)
	}
}

func (c *stressCheckout) forgets(t *testing.T) {
	kept.Drop(c.Project, git.CurrentBranch(c.Project))
	kept.Forget(c.At)

	c.wrote++
	file := filepath.Join(c.At, "shop", "goods", fmt.Sprintf("forgot%d_%d.py", c.Named, c.wrote))

	written := stressMine(c.Named)
	if err := os.WriteFile(file, []byte(written), 0o644); err != nil {
		t.Fatal(err)
	}

	c.mine = append(c.mine, file)
	c.tells(t, file, 1, lines(written), true)
}

func (c *stressCheckout) handsIn(t *testing.T) {
	c.own.Lock()
	defer c.own.Unlock()
	defer flying()()

	stressHeld(t, c, c.at, c.Project, "", false, stressTake(t))

	if _, held := c.holding(); !held {
		return
	}

	answer, asked := warm.Asked(c.Project, warm.Request{
		Judge: []string{"--changes", "--no-checklist", "--parallel=2"},
		In:    c.Told,
	})

	if !asked {
		t.Fatalf("move %d of checkout %d: its hand-in judging was DECLINED, so it reads the whole tree in its "+
			"own process: %s", c.at, c.Named, answer.Because)
	}

	for _, named := range stressNamedIn(answer.Out) {
		if !c.owns(named) {
			t.Fatalf("move %d of checkout %d: its served hand-in reports %s, which is not its own (it owns %q)\n%s",
				c.at, c.Named, named, c.under(), answer.Out)
		}
	}

	c.countsWhatReallyDiffers(t)
}

func (c *stressCheckout) countsWhatReallyDiffers(t *testing.T) {
	named, base, held := baseFor(c.Project)
	if !held {
		return
	}

	checkout := checkoutOf(named.project, c.At)

	var asked []string
	filepath.WalkDir(c.At, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || strings.Contains(path, "/.git/") {
			return nil
		}
		if source.Judges(path) {
			asked = append(asked, path)
		}

		return nil
	})

	differs, covered := differingFrom(base, named, checkout, asked)
	if !covered {
		return
	}

	for _, file := range differs.Unread {
		own := file
		if under, inside := strings.CutPrefix(file, c.At+"/"); inside {
			own = filepath.Join(c.Project, under)
		}

		if _, known := base.Holds(own); known {
			t.Fatalf("move %d of checkout %d: its judging counts %s as a file the tree never read, while the tree "+
				"holds the project's own copy of it", c.at, c.Named, file)
		}

		if !stressHeldLanguage(base, file) {
			t.Fatalf("move %d of checkout %d: its judging counts %s as a file the tree never read, and the tree "+
				"holds no file of that language at all, so that count can never be settled", c.at, c.Named, file)
		}
	}
}

func stressHeldLanguage(base *warm.Base, file string) bool {
	for _, held := range base.Languages() {
		if named, known := source.Of(held); known && source.OfFile(file) == named {
			return true
		}
	}

	return false
}

func stressNamedIn(said string) []string {
	var named []string
	for _, word := range strings.Fields(said) {
		if at := strings.LastIndex(word, ".py:"); at > 0 {
			named = append(named, word[:at+3])
		}
	}

	return named
}

func (c *stressCheckout) holding() (string, bool) {
	project := git.ProjectRoot(c.Project)
	branch := git.CurrentBranch(project)
	_, held := kept.Holds(project, branch)

	return branch, held
}

func (c *stressCheckout) renames(t *testing.T) {
	was := c.one()
	if was == "" {
		return
	}

	c.wrote++
	into := filepath.Join(c.At, "shop", "goods", fmt.Sprintf("moved%d_%d.py", c.Named, c.wrote))

	if c.draw.Intn(2) == 0 {
		into = filepath.Join(c.At, "shop", "goods", fmt.Sprintf("part%d", c.Named), fmt.Sprintf("moved%d.py", c.wrote))
		if err := os.MkdirAll(filepath.Dir(into), 0o755); err != nil {
			t.Fatal(err)
		}
	}

	if err := os.Rename(was, into); err != nil {
		return
	}

	c.mine = slices.DeleteFunc(c.mine, func(path string) bool { return path == was })
	c.mine = append(c.mine, into)

	c.tells(t, into, 1, lines(stressMine(c.Named)), false)
}

func (c *stressCheckout) writesOddly(t *testing.T) {
	c.wrote++

	switch c.draw.Intn(4) {
	case 0:
		big := filepath.Join(c.At, "shop", "goods", fmt.Sprintf("big%d_%d.py", c.Named, c.wrote))
		if err := os.WriteFile(big, []byte(strings.Repeat("x = 1\n", 40000)), 0o644); err != nil {
			t.Fatal(err)
		}

		c.tells(t, big, 1, 40000, false)
	case 1:
		shut := filepath.Join(c.At, "shop", "goods", fmt.Sprintf("shut%d_%d.py", c.Named, c.wrote))
		if err := os.WriteFile(shut, []byte(stressMine(c.Named)), 0o000); err != nil {
			t.Fatal(err)
		}

		c.tells(t, shut, 1, 10, false)
	case 2:
		aside := filepath.Join(c.At, "aside", fmt.Sprintf("aside%d_%d.py", c.Named, c.wrote))
		if err := os.MkdirAll(filepath.Dir(aside), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(aside, []byte(stressMine(c.Named)), 0o644); err != nil {
			t.Fatal(err)
		}

		c.tells(t, aside, 1, 10, false)
	default:
		for named, text := range map[string]string{
			fmt.Sprintf("shop/other%d_%d.go", c.Named, c.wrote):  "package shop\n\nfunc Other() int { return 0 }\n",
			fmt.Sprintf("shop/notes%d_%d.md", c.Named, c.wrote):  "# notes\n",
			fmt.Sprintf("shop/data%d_%d.json", c.Named, c.wrote): "{}\n",
		} {
			file := filepath.Join(c.At, named)
			if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
				t.Fatal(err)
			}

			c.tells(t, file, 1, 3, false)
		}
	}
}

func (c *stressCheckout) raises(t *testing.T) {
	events := []string{"ticked", "idle", "command_starting", "session_started", "tool_starting", "subagent_started", "plugin.updated", "worker_delivering"}
	event := events[c.draw.Intn(len(events))]

	c.own.Lock()
	defer c.own.Unlock()
	defer flying()()

	stressHeld(t, c, c.at, c.Project, "", false, stressTake(t))

	sendEvent(t, c.Service, map[string]any{
		"v": 1, "event": event, "session": fmt.Sprintf("s%d", c.Named), "environment": "main",
		"agent": c.Named, "cwd": c.Told,
		"data": map[string]any{"command": "git status", "tool": "Bash"},
	})

	stressHeld(t, c, c.at, c.Project, "", false, c.awaited(t, "", false))
}

func (c *stressCheckout) commits(t *testing.T) {
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"})
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "work"})
}

func (c *stressCheckout) switches(t *testing.T) {
	if c.At != c.Project {
		c.edits(t)

		return
	}

	c.commits(t)
	c.runs(t, []string{"checkout", "-q", "-B", fmt.Sprintf("main-%d", c.draw.Intn(3))})
}

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

	cut := []string{"worktree", "add", "-q", "-b", fmt.Sprintf("worker-%d-cut-%d-%d", c.Named, c.at, c.wrote), c.At}
	if out, err := exec.Command("git", append([]string{"-C", c.Project}, cut...)...).CombinedOutput(); err != nil {
		t.Fatalf("the worktree %s could not be cut again: %s", c.At, out)
	}

	c.mine = []string{filepath.Join(c.At, "shop", "goods", "thing0.py")}
}

func (c *stressCheckout) abandons(t *testing.T) {
	if c.At == c.Project || len(c.mine) == 0 {
		c.commits(t)

		return
	}

	c.commits(t)

	if out, err := exec.Command("git", "-C", c.Project, "worktree", "remove", "--force", c.At).CombinedOutput(); err != nil {
		t.Logf("worktree %s was not removed: %s", c.At, out)

		return
	}

	gone := c.mine[0]
	c.mine = nil
	c.tells(t, gone, 1, 3, false)

	if err := os.RemoveAll(c.At); err != nil {
		t.Fatal(err)
	}

	if out, err := exec.Command("git", "-C", c.Project, "worktree", "prune").CombinedOutput(); err != nil {
		t.Logf("worktrees were not pruned: %s", out)
	}

	cut := []string{"worktree", "add", "-q", "-b", fmt.Sprintf("worker-%d-again-%d-%d", c.Named, c.at, c.wrote), c.At}
	if out, err := exec.Command("git", append([]string{"-C", c.Project}, cut...)...).CombinedOutput(); err != nil {
		t.Fatalf("the worktree %s could not be cut again: %s", c.At, out)
	}

	c.mine = []string{filepath.Join(c.At, "shop", "goods", "thing0.py")}
}

func (c *stressCheckout) runs(t *testing.T, args []string) {
	if out, err := exec.Command("git", append([]string{"-C", c.At}, args...)...).CombinedOutput(); err != nil {
		t.Logf("git %v in %s: %s", args, filepath.Base(c.At), out)
	}
}

func (c *stressCheckout) writes(t *testing.T, many int) {
	for at := 0; at < many; at++ {
		c.wrote++
		file := filepath.Join(c.At, "shop", "goods", fmt.Sprintf("made%d_%d.py", c.Named, c.wrote))

		written := stressMine(c.Named)
		if err := os.WriteFile(file, []byte(written), 0o644); err != nil {
			t.Fatal(err)
		}

		c.mine = append(c.mine, file)
		c.tells(t, file, 1, lines(written), true)
	}
}

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

	c.tells(t, file, lines(string(was)), lines(string(was))+lines(appended), false)
}

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

	c.tells(t, file, 0, 0, false)
}

func (c *stressCheckout) deletes(t *testing.T) {
	file := c.one()
	if file == "" {
		return
	}

	if !stressWasTold(stressUnder(c.Project, file), stressUnder(c.At, file)) {
		return
	}

	if err := os.Remove(file); err != nil {
		return
	}

	c.mine = slices.DeleteFunc(c.mine, func(path string) bool { return path == file })
}

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

func (c *stressCheckout) one() string {
	if len(c.mine) == 0 {
		return ""
	}

	return c.mine[c.draw.Intn(len(c.mine))]
}

func (c *stressCheckout) tells(t *testing.T, file string, from, to int, owed bool) {
	var added []any
	for line := from; line <= to; line++ {
		added = append(added, map[string]any{"line_type": "add", "line": line, "text": "class Mine:"})
	}

	data := map[string]any{"kind": "edit", "added": len(added), "removed": 0, "tool_use": c.wrote + 1, "lines": added}
	against := c.Project

	if from == 1 && owed {
		data["kind"] = "create"
		delete(data, "lines")
	}

	switch c.draw.Intn(3) {
	case 0:
		under, err := filepath.Rel(c.Project, file)
		if err != nil {
			t.Fatal(err)
		}

		data["path"] = under
	case 1:
		data["input"] = map[string]any{"file_path": file}
		against = c.At
	default:
		under, err := filepath.Rel(c.At, file)
		if err != nil {
			t.Fatal(err)
		}

		data["path"] = under
		data["cwd"] = c.Told
		against = c.At
	}

	c.own.Lock()
	defer c.own.Unlock()
	defer flying()()

	stressHeld(t, c, c.at, against, "", false, stressTake(t))

	sendEvent(t, c.Service, map[string]any{
		"v": 1, "event": "file_edited", "session": fmt.Sprintf("s%d", c.Named), "environment": "main",
		"agent": c.Named, "cwd": c.Told,
		"data": data,
	})

	stressHeld(t, c, c.at, against, file, owed, c.awaited(t, file, owed))
}

func (c *stressCheckout) awaited(t *testing.T, file string, owed bool) []map[string]any {
	t.Helper()

	var mine []map[string]any

	for until := time.Now().Add(*stressWaits); time.Now().Before(until); time.Sleep(20 * time.Millisecond) {
		mine = append(mine, stressTake(t)...)

		if !owed || stressWasTold(stressUnder(c.Project, file), stressUnder(c.At, file)) {
			break
		}
	}

	return mine
}

var stressToldPaths = map[string]bool{}

func stressTake(t *testing.T) []map[string]any {
	t.Helper()

	taken := stressDrain(t, os.Getenv(pluginData))
	for _, answer := range taken {
		for _, named := range stressReported(answer) {
			stressToldPaths[named] = true
		}
	}

	return taken
}

func stressWasTold(paths ...string) bool {
	for _, named := range paths {
		if stressToldPaths[named] {
			return true
		}
	}

	return false
}

func stressDrain(t *testing.T, data string) []map[string]any {
	t.Helper()

	queue, _ := Queue4In(data)
	files, _ := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
	sort.Strings(files)

	var taken []map[string]any

	for _, path := range files {
		text, err := os.ReadFile(path)
		if err != nil {
			continue
		}

		var answer map[string]any
		if json.Unmarshal(text, &answer) != nil {
			continue
		}

		taken = append(taken, answer)

		if err := os.Remove(path); err != nil {
			t.Fatal(err)
		}
	}

	return taken
}

var stressWaits = flag.Duration("stress.waits", 3*time.Minute, "how long a move waits for its own answer before calling the plugin silent")

func lines(text string) int {
	return len(strings.Split(strings.TrimRight(text, "\n"), "\n"))
}

func stressMine(named int) string {
	at := int(time.Now().UnixNano() % 1000000)

	return fmt.Sprintf(`from shop.goods.rebuild import RebuildKind


class Mine%dAt%d(RebuildKind):
    number = %d

    def deepened%d(self, order%d, rate%d=None):
        if order%d is not None:
            for line%d in order%d.lines:
                if line%d.total > %d:
                    if rate%d is not None:
                        return line%d.total * rate%d
                    else:
                        return line%d.total
        return %d
`, named, at, named, at, at, at, at, at, at, at, at, at, at, at, at, at)
}

var toldPaths = regexp.MustCompile(`(?:^|\s)([\w./-]+\.py):(\d+)`)

var cardPaths = regexp.MustCompile(`([\w./-]+\.py)`)

func stressHeld(t *testing.T, mine *stressCheckout, move int, against, file string, owed bool, answers []map[string]any) {
	t.Helper()

	told := false

	for _, answer := range answers {
		whose, known := stressWhose(asAgent(answer["agent"]))
		if !known {
			t.Fatalf("move %d of checkout %d: an answer came back for agent %d, which is no checkout of this run\n%s",
				move, mine.Named, asAgent(answer["agent"]), asText(answer["tell"]))
		}

		for _, named := range stressReported(answer) {
			answered.Add(1)

			if !stressStands(whose, named) {
				t.Fatalf("move %d of checkout %d: an answer to checkout %d reports a sin in %s, which is a file "+
					"in neither its project nor its own checkout\n%v", move, mine.Named, whose.Named, named, answer)
			}
		}

		for _, named := range stressSaidOf(answer) {
			if named == stressUnder(against, file) || named == stressUnder(mine.Project, file) || named == stressUnder(mine.At, file) {
				told = true
			}

			if !whose.owns(named) {
				t.Fatalf("move %d of checkout %d: checkout %d is told about %s, which is not its own (it owns "+
					"%q)\n%v", move, mine.Named, whose.Named, named, whose.under(), answer)
			}
		}
	}

	if !told && stressWasTold(stressUnder(against, file), stressUnder(mine.Project, file), stressUnder(mine.At, file)) {
		told = true
	}

	if owed && !told {
		settings, _ := config.Load(mine.Project)
		found, _, unread := marksIn(Marks(mine.Project), file, allRules(t, mine.Project, settings), git.Everywhere())

		var names []string
		for _, mark := range found {
			names = append(names, strings.TrimSpace(mark.Found()))
		}

		t.Fatalf("move %d of checkout %d: it wrote a sin in %s and was told nothing about it; %d answers came "+
			"back. A judging of that file alone finds %d sins %v (unread %v)\n%v",
			move, mine.Named, stressUnder(against, file), len(answers), len(found), names, unread, answers)
	}
}

var stressRunning = map[int]*stressCheckout{}

func stressWhose(agent int) (*stressCheckout, bool) {
	held, known := stressRunning[agent]

	return held, known
}

func stressWhere(whose *stressCheckout, named string) string {
	if info, err := os.Stat(filepath.Join(whose.Project, named)); err == nil && info.Mode().IsRegular() {
		return filepath.Join(whose.Project, named)
	}

	return filepath.Join(whose.At, named)
}

func stressStands(whose *stressCheckout, named string) bool {
	info, err := os.Stat(stressWhere(whose, named))

	return err == nil && info.Mode().IsRegular()
}

func (c *stressCheckout) owns(named string) bool {
	if strings.HasPrefix(named, c.under()) {
		return true
	}

	info, err := os.Stat(filepath.Join(c.At, named))

	return err == nil && info.Mode().IsRegular()
}

func stressSaidOf(answer map[string]any) []string {
	var named []string
	for _, found := range toldPaths.FindAllStringSubmatch(asText(answer["tell"]), -1) {
		named = append(named, found[1])
	}

	return named
}

func stressReported(answer map[string]any) []string {
	named := stressSaidOf(answer)

	cards, _ := answer["cards"].([]any)
	for _, card := range cards {
		said, _ := card.(map[string]any)

		label := asText(said["label"]) + " " + asText(said["brief"])
		if strings.Contains(label, "is clean") {
			continue
		}

		for _, part := range []string{"label", "brief", "detail"} {
			named = append(named, cardPaths.FindAllString(asText(said[part]), -1)...)
		}
	}

	return named
}

func stressUnder(project, file string) string {
	under, err := filepath.Rel(project, file)
	if err != nil {
		return file
	}

	return under
}

func stressEditedFiles() []string {
	var files []string
	for worker := 1; worker <= *stressWorkers; worker++ {
		for edit := 0; edit < *stressEdits; edit++ {
			files = append(files, fmt.Sprintf("%d\x00thing%d.py", worker, edit))
		}
	}

	return files
}

func stressWorstWait(answers []map[string]any) time.Duration {
	var worst time.Duration
	for _, answer := range answers {
		if held := time.Duration(said(answer, "judged_total_ms")) * time.Millisecond; held > worst {
			worst = held
		}
	}

	return worst
}
