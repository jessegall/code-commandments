package judge

import (
	"fmt"
	"io"
	"runtime"
	"sort"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/catalog"
)

// profile is one detector's run: how long it took, what it found, and the memory it grew by.
type profile struct {
	name    string
	seconds float64
	matches int
	bytes   int64
}

// Benchmark runs the tasks one after another, timing each, and answers their judgement with the profiles.
func Benchmark(tasks []Task, warn io.Writer) (Judgement, []profile) {
	var judgement Judgement
	var profiles []profile

	for _, task := range tasks {
		var before, after runtime.MemStats
		runtime.ReadMemStats(&before)
		start := time.Now()
		result := try(task, warn)
		seconds := time.Since(start).Seconds()
		runtime.ReadMemStats(&after)

		for i := range result.findings {
			result.findings[i].Twins = nil
		}

		judgement.Findings = append(judgement.Findings, result.findings...)

		if result.skipped != "" {
			judgement.Skipped = append(judgement.Skipped, result.skipped)
		}

		profiles = append(profiles, profile{catalog.Name(task.Detector), seconds, len(result.findings), int64(after.HeapAlloc) - int64(before.HeapAlloc)})
	}

	return judgement, profiles
}

// Profiles lays the profiles out slowest first, after how long the parse took.
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
		fmt.Sprintf("  %-42s %9s %6s %7s %8s", "detector", "time", "%", "matches", "shards"),
		"  " + strings.Repeat("─", 76),
	}

	for _, each := range profiles {
		share := 0.0
		if total > 0 {
			share = each.seconds / total * 100
		}

		lines = append(lines, fmt.Sprintf("  %-42s %8.3fs %5.1f %7d %8s   %s", each.name, each.seconds, share, each.matches, "·", megabytes(each.bytes)))
	}

	return strings.Join(lines, "\n") + "\n"
}

func megabytes(bytes int64) string {
	if bytes > -1024*1024 && bytes < 1024*1024 {
		return fmt.Sprintf("%+.0fK", float64(bytes)/1024)
	}

	return fmt.Sprintf("%+.1fM", float64(bytes)/1024/1024)
}
