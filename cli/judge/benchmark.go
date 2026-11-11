package judge

import (
	"fmt"
	"io"
	"runtime"
	"sort"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
)

// profile is one detector's run: how long it took, what it found, how many times it went over every file of the tree, and the memory it grew by.
type profile struct {
	name    string
	seconds float64
	matches int
	passes  int64
	bytes   int64
}

func Benchmark(tasks []Task, warn io.Writer) (Judgement, []profile) {
	var judgement Judgement
	var profiles []profile

	for _, task := range tasks {
		var before, after runtime.MemStats
		runtime.ReadMemStats(&before)
		counted := engine.Counted()
		start := time.Now()
		result := try(task, warn)
		seconds := time.Since(start).Seconds()
		passes := engine.Since(counted).Walked / int64(max(1, len(task.Codebase.Files())))
		runtime.ReadMemStats(&after)

		for i := range result.findings {
			result.findings[i].Twins = nil
		}

		judgement.Findings = append(judgement.Findings, result.findings...)

		if result.skipped != "" {
			judgement.Skipped = append(judgement.Skipped, result.skipped)
		}

		profiles = append(profiles, profile{catalog.Name(task.Detector), seconds, len(result.findings), passes, int64(after.HeapAlloc) - int64(before.HeapAlloc)})
	}

	return judgement, profiles
}

func Profiles(profiles []profile, parseSeconds float64) string {
	sort.SliceStable(profiles, func(i, j int) bool {
		return profiles[i].seconds > profiles[j].seconds
	})

	total := 0.0

	for _, each := range profiles {
		total += each.seconds
	}

	lines := []string{
		"",
		fmt.Sprintf("  parse: %6.2fs   detect: %6.2fs   (sequential, profiling)", parseSeconds, total),
		fmt.Sprintf("  %-42s %9s %6s %7s %8s", "detector", "time", "%", "matches", "passes"),
		"  " + strings.Repeat("─", 76),
	}

	for _, each := range profiles {
		share := 0.0
		if total > 0 {
			share = each.seconds / total * 100
		}

		lines = append(lines, fmt.Sprintf("  %-42s %8.3fs %5.1f %7d %8d   %s", each.name, each.seconds, share, each.matches, each.passes, megabytes(each.bytes)))
	}

	return strings.Join(lines, "\n") + "\n"
}

func megabytes(bytes int64) string {
	if bytes > -1024*1024 && bytes < 1024*1024 {
		return fmt.Sprintf("%+.0fK", float64(bytes)/1024)
	}

	return fmt.Sprintf("%+.1fM", float64(bytes)/1024/1024)
}
