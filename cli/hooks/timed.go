package hooks

import (
	"fmt"
	"os"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Timings is what each rule cost over a session, kept only while the setting asks for it: the rules are where a
// judging's time goes once the tree is warm, and until they are split nobody can say which of them is worth
// changing. The split a judging cannot see on its own is what a rule spends building a whole-program analysis
// against walking the tree, which shows as the FIRST rule of a kind paying for every one after it.
type Timings struct {
	mu    sync.Mutex
	spent map[string]time.Duration
	work  map[string]engine.Work
	asked map[string]int
}

var timings = Timings{spent: map[string]time.Duration{}, work: map[string]engine.Work{}, asked: map[string]int{}}

func timed(rule detectors.Detector, took time.Duration, did engine.Work) {
	if os.Getenv("COMMANDMENTS_TIME_RULES") == "" {
		return
	}

	timings.mu.Lock()
	defer timings.mu.Unlock()

	named := catalog.Name(rule)
	timings.spent[named] += took
	timings.asked[named]++

	held := timings.work[named]
	held.Nodes += did.Nodes
	held.Checks += did.Checks
	held.Queries += did.Queries
	timings.work[named] = held
}

func TimedRules() string {
	timings.mu.Lock()
	defer timings.mu.Unlock()

	if len(timings.spent) == 0 {
		return ""
	}

	named := make([]string, 0, len(timings.spent))
	for rule := range timings.spent {
		named = append(named, rule)
	}

	sort.Slice(named, func(a, b int) bool { return timings.work[named[a]].Nodes > timings.work[named[b]].Nodes })

	whole := engine.Work{}
	spent := time.Duration(0)
	for _, rule := range named {
		whole.Nodes += timings.work[rule].Nodes
		whole.Checks += timings.work[rule].Checks
		whole.Queries += timings.work[rule].Queries
		spent += timings.spent[rule]
	}

	said := []string{fmt.Sprintf("%d rules touched %d nodes with %d checks over %d queries, and took %dms; the most work first:",
		len(named), whole.Nodes, whole.Checks, whole.Queries, spent.Milliseconds())}

	for _, rule := range named[:min(20, len(named))] {
		did := timings.work[rule]
		said = append(said, fmt.Sprintf("  %9d nodes %9d checks %5d queries over %d asks (%6.1fms)  %s",
			did.Nodes, did.Checks, did.Queries, timings.asked[rule],
			float64(timings.spent[rule].Microseconds())/1000, rule))
	}

	return strings.Join(said, "\n")
}
