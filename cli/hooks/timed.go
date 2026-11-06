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
	held.Analyses += did.Analyses
	held.Parts += did.Parts
	held.Visited += did.Visited
	held.Resolved += did.Resolved
	held.Files += did.Files
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

	sort.Slice(named, func(a, b int) bool { return timings.work[named[a]].Points() > timings.work[named[b]].Points() })

	whole := engine.Work{}
	spent := time.Duration(0)
	for _, rule := range named {
		did := timings.work[rule]
		whole.Nodes += did.Nodes
		whole.Checks += did.Checks
		whole.Queries += did.Queries
		whole.Analyses += did.Analyses
		whole.Parts += did.Parts
		whole.Visited += did.Visited
		whole.Resolved += did.Resolved
		whole.Files += did.Files
		spent += timings.spent[rule]
	}

	said := []string{fmt.Sprintf("%d rules cost %d points in all: %d nodes, %d checks, %d queries, %d walks, "+
		"%d files read, %d answers kept per file and %d whole-program analyses built, and took %dms. A file read "+
		"counts ten and a whole-program analysis a thousand, because that is the order of what they really cost, "+
		"while an answer kept against one file counts one. The most compute first:",
		len(named), whole.Points(), whole.Nodes, whole.Checks, whole.Queries, whole.Visited, whole.Files,
		whole.Parts, whole.Analyses, spent.Milliseconds())}

	for _, rule := range named[:min(20, len(named))] {
		did := timings.work[rule]
		said = append(said, fmt.Sprintf("  %9d points %6d nodes %9d walks %6d files %5d parts %3d analyses over %d asks (%7.1fms)  %s",
			did.Points(), did.Nodes, did.Visited, did.Files, did.Parts, did.Analyses, timings.asked[rule],
			float64(timings.spent[rule].Microseconds())/1000, rule))
	}

	return strings.Join(said, "\n")
}
