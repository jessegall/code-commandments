package hooks

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
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
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/walk"
)

var (
	stress        = flag.Bool("stress", false, "run the stress harness rather than skipping it")
	stressFiles   = flag.Int("stress.files", 400, "how many source files the project it builds holds")
	stressWorkers = flag.Int("stress.workers", 3, "how many workers edit and hand in at once")
	stressEdits   = flag.Int("stress.edits", 3, "how many files each worker edits")
	stressHandIn  = flag.Bool("stress.hand-in", true, "run each worker's hand-in judging in the same window")
	stressWithin  = flag.Duration("stress.within", 10*time.Second,
		"how long after the last edit the last answer may land before the run fails")
	stressAsks    = flag.Bool("stress.asks", false, "time one ask of 1, 2, 3 and 5 files through the warm bridge instead of the load")
	stressTimings = flag.Bool("stress.timings", false, "print what each rule cost over the run, the heaviest first")
	stressAhead   = flag.Bool("stress.ahead", true, "parse each edited file when its event arrives, together with every file waiting then")
	stressApart   = flag.Duration("stress.apart", 0, "how long a worker waits between its edits; a real agent edits seconds apart, not all at once")
)

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
	answerWith(Stamped(line))
	t.Logf("the whole answer to a stop took %v", time.Since(whole).Round(time.Millisecond))
}

func edits(answers []map[string]any) []map[string]any {
	var mine []map[string]any
	for _, answer := range answers {
		if strings.Contains(asText(answer["whisper"]), "thing") {
			mine = append(mine, answer)
		}
	}

	return mine
}

func stressTold(t *testing.T, answers []map[string]any) {
	t.Helper()

	var said strings.Builder
	for _, answer := range answers {
		said.WriteString(asText(answer["whisper"]))
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

	stop := stressBridgeUp(t, project)

	if *stressRestarts <= 0 {
		return
	}

	go func() {
		for each := 0; each < *stressRestarts; each++ {
			time.Sleep(*stressRestartAfter)

			stop()
			t.Logf("the bridge was stopped and started again, restart %d of %d, as one that died would be",
				each+1, *stressRestarts)

			stop = stressBridgeUp(t, project)
		}
	}()
}

func stressBridgeUp(t *testing.T, project string) func() {
	t.Helper()

	bridged, needed, err := scan.Python.Reach.Own(scan.Python, []string{project})
	if err != nil || !needed {
		t.Fatalf("the python bridge cannot be kept up here: needed %v, %v", needed, err)
	}

	path := bridge.ServiceSocket(scan.Python.Kind, project)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		bridged.Close()
		t.Fatalf("the bridge service cannot listen on %s: %v", path, err)
	}

	go bridge.Answer(listener, bridged)

	stopped := false
	stop := func() {
		if stopped {
			return
		}

		stopped = true
		listener.Close()
		bridged.Close()
	}

	t.Cleanup(stop)

	return stop
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
	if under, inside := walk.Inside(root, file); inside {
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

func stressTellsOf(folder, project, linked string) string {
	if folder == project {
		return linked
	}

	if under, inside := strings.CutPrefix(folder, project+"/"); inside {
		return filepath.Join(linked, under)
	}

	return folder
}

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
	stressOracle    = flag.Int("stress.oracle", 0, "check one in every N answers against a fresh read of the whole worktree; 0 never does")
	stressRestarts  = flag.Int("stress.restarts", 0, "stop the language bridge and start it again this many times during the run, as one that died would be")

	stressRestartAfter = flag.Duration("stress.restart-after", 20*time.Second, "how long between the bridge restarts")
	stressMoves        = flag.Int("stress.moves", 1000, "how many moves each checkout makes")
	stressSeed         = flag.Int64("stress.seed", 0, "the seed the moves are drawn from; 0 draws one and prints it")
)

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
	away  []string
	wrote int
	at    int
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
	for _, word := range strings.Fields(plainly.ReplaceAllString(said, "")) {
		if at := strings.LastIndex(word, ".py:"); at > 0 {
			named = append(named, word[:at+3])
		}
	}

	return named
}

var plainly = regexp.MustCompile("\x1b\\[[0-9;]*m")

func (c *stressCheckout) commits(t *testing.T) {
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"})
	c.runs(t, []string{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "work"})
}

func (c *stressCheckout) recuts(t *testing.T) {
	if c.At == c.Project {
		c.commits(t)

		return
	}

	c.commits(t)

	for _, file := range c.mine {
		c.tookAway(file)
	}

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

func (c *stressCheckout) runs(t *testing.T, args []string) {
	if out, err := exec.Command("git", append([]string{"-C", c.At}, args...)...).CombinedOutput(); err != nil {
		t.Logf("git %v in %s: %s", args, filepath.Base(c.At), out)
	}
}

func (c *stressCheckout) deletes(t *testing.T) {
	file := c.one()
	if file == "" {
		return
	}

	if !stressWasTold(stressUnder(c.Project, file), stressUnder(c.At, file)) {
		return
	}

	c.tookAway(file)

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

func (c *stressCheckout) awaited(t *testing.T, file string, owed bool) []map[string]any {
	t.Helper()

	var mine []map[string]any

	for until := time.Now().Add(*stressWaits); time.Now().Before(until); time.Sleep(20 * time.Millisecond) {
		mine = append(mine, stressTakeFor(t, c.Named)...)

		if !owed || stressWasTold(stressUnder(c.Project, file), stressUnder(c.At, file)) {
			break
		}
	}

	return mine
}

var stressToldPaths = map[string]bool{}

func stressTake(t *testing.T) []map[string]any {
	t.Helper()

	return stressTakeFor(t, 0)
}

func stressTakeFor(t *testing.T, agent int) []map[string]any {
	t.Helper()

	taken := stressDrain(t, os.Getenv(pluginData), agent)
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

func stressDrain(t *testing.T, data string, agent int) []map[string]any {
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

		if whose := whoseAnswer(answer); agent != 0 && whose != 0 && whose != agent {
			continue
		}

		taken = append(taken, answer)

		if err := os.Remove(path); err != nil && !os.IsNotExist(err) {
			t.Fatal(err)
		}
	}

	return taken
}

func whoseAnswer(answer map[string]any) int {
	switch named := answer["agent"].(type) {
	case float64:
		return int(named)
	case int:
		return named
	}

	return 0
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

var answers struct {
	sync.Mutex
	took []time.Duration
}

func stressAnswering(took time.Duration) {
	answers.Lock()
	defer answers.Unlock()

	answers.took = append(answers.took, took)
}

func stressAnsweringSaid() string {
	answers.Lock()
	defer answers.Unlock()

	if len(answers.took) == 0 {
		return "no answer was timed"
	}

	took := slices.Clone(answers.took)
	slices.Sort(took)

	at := func(part float64) time.Duration { return took[min(int(float64(len(took))*part), len(took)-1)] }

	return fmt.Sprintf("the time from an edit reaching the service to its answer, over %d edits: "+
		"half within %s, 95 in 100 within %s, the slowest %s. It is read, never asserted: the machine is busy "+
		"with other work and a clock would fail this run for that alone.",
		len(took), at(0.5), at(0.95), took[len(took)-1])
}

var stressSent = map[string]string{}

var stressRunning = map[int]*stressCheckout{}

var stressForgot atomic.Int64

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

	if info, err := os.Stat(filepath.Join(c.At, named)); err == nil && info.Mode().IsRegular() {
		return true
	}

	return c.wroteItItself(named)
}

func (c *stressCheckout) wroteItItself(named string) bool {
	for _, file := range c.mine {
		if stressUnder(c.At, file) == named {
			return true
		}
	}

	return c.tookItAway(named)
}

func (c *stressCheckout) tookAway(file string) {
	c.away = append(c.away, stressUnder(c.At, file))
}

func (c *stressCheckout) tookItAway(named string) bool {
	return slices.Contains(c.away, named)
}

func stressSaidOf(answer map[string]any) []string {
	var named []string
	for _, found := range toldPaths.FindAllStringSubmatch(asText(answer["whisper"]), -1) {
		named = append(named, found[1])
	}

	return named
}

func stressLinesOf(answers []map[string]any, named string) []string {
	var lines []string
	for _, answer := range answers {
		for _, found := range toldPaths.FindAllStringSubmatch(asText(answer["whisper"]), -1) {
			if found[1] == named {
				lines = append(lines, found[2])
			}
		}
	}

	return lines
}

func stressRefusedIn(answer map[string]any) []string {
	tell := asText(answer["whisper"])
	if !strings.Contains(tell, "nothing of this edit was judged") {
		return nil
	}

	var named []string
	for _, found := range cardPaths.FindAllStringSubmatch(tell, -1) {
		named = append(named, found[1])
	}

	return named
}

func stressReported(answer map[string]any) []string {
	named := stressSaidOf(answer)

	cards, _ := answer["raises"].([]any)
	for _, card := range cards {
		said, _ := card.(map[string]any)

		label := asText(said["brief"])
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
func asFloat(held any) float64 {
	number, _ := held.(float64)

	return number
}

func recordedLines(t *testing.T, data string) string {
	t.Helper()

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		return ""
	}

	return string(said)
}
