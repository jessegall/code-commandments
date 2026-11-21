package hooks

import (
	"flag"
	"fmt"
	"math/rand"
	"os"
	"path/filepath"
	"regexp"
	"sync"
	"sync/atomic"
	"testing"
	"time"
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

const stressEnvironment = "development"

func stressWorktreeIn(project string, worker int) string {
	if worker%2 == 1 {
		return filepath.Join(project, ".journal", "worktrees", stressEnvironment, fmt.Sprintf("worker-%d", worker))
	}

	return filepath.Join(filepath.Dir(project), filepath.Base(project)+fmt.Sprintf("-worker-%d", worker))
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

var plainly = regexp.MustCompile("\x1b\\[[0-9;]*m")

var stressToldPaths = map[string]bool{}

var stressWaits = flag.Duration("stress.waits", 3*time.Minute, "how long a move waits for its own answer before calling the plugin silent")

var toldPaths = regexp.MustCompile(`(?:^|\s)([\w./-]+\.py):(\d+)`)

var cardPaths = regexp.MustCompile(`([\w./-]+\.py)`)

var answers struct {
	sync.Mutex
	took []time.Duration
}

var stressSent = map[string]string{}

var stressRunning = map[int]*stressCheckout{}

var stressForgot atomic.Int64

func recordedLines(t *testing.T, data string) string {
	t.Helper()

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		return ""
	}

	return string(said)
}
