// Package judge is the `judge` command: it scans a tree, runs every enabled detector over it, and reports
// the sins grouped by the skill that fixes each, into the console and a checklist.
package judge

import (
	"fmt"
	"github.com/jessegall/code-commandments/cli/custom"
	"io"
	"runtime"
	"slices"
	"sync"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Judgement is what a run found, and the rules that broke and could not run.
type Judgement struct {
	Findings []engine.Finding
	Skipped  []string
}

// Task is one detector and the codebase it judges.
type Task struct {
	Detector detectors.Detector
	Codebase *engine.Codebase
}

// Run runs every task across up to parallel workers, capped at the machine's cores and the number of
// tasks. A detector that panics is skipped and named, and every other one still runs; the findings come
// back in task order whatever order the workers finished in.
func Run(tasks []Task, parallel int, progress *cli.Progress, warn io.Writer) Judgement {
	results := make([]attempt, len(tasks))
	workers := max(1, min(parallel, runtime.NumCPU(), len(tasks)))
	next := make(chan int)

	var wait sync.WaitGroup
	var drawing sync.Mutex

	progress.Start(len(tasks))

	for range workers {
		wait.Add(1)

		go func() {
			defer wait.Done()

			for index := range next {
				results[index] = try(tasks[index], warn)

				drawing.Lock()
				progress.Advance("")
				drawing.Unlock()
			}
		}()
	}

	for index := range tasks {
		next <- index
	}

	close(next)
	wait.Wait()

	var judgement Judgement

	for _, result := range results {
		judgement.Findings = append(judgement.Findings, result.findings...)

		if result.skipped != "" {
			judgement.Skipped = append(judgement.Skipped, result.skipped)
		}
	}

	return judgement
}

// attempt is one detector's run: its findings, or its name when it broke.
type attempt struct {
	findings []engine.Finding
	skipped  string
}

var warning sync.Mutex

func try(task Task, warn io.Writer) (result attempt) {
	name := catalog.Name(task.Detector)

	defer func() {
		failure := recover()
		if failure == nil {
			return
		}

		warning.Lock()
		fmt.Fprintf(warn, "⚠ %s failed and was skipped — everything else still ran: %v\n", name, failure)
		warning.Unlock()

		result = attempt{skipped: name}
	}()

	return attempt{findings: findings(task.Detector, task.Detector.Find(task.Codebase))}
}

// findings are the matches as findings, each naming the other members of its recurring group.
func findings(detector detectors.Detector, matches []engine.Match) []engine.Finding {
	sin := detector.Sin().Definition()
	groups := groupsOf(detector, matches)
	found := make([]engine.Finding, 0, len(matches))

	for i, match := range matches {
		location := match.Location()
		var twins []string

		for _, member := range groups[i] {
			if member != location {
				twins = append(twins, member)
			}
		}

		found = append(found, engine.Finding{
			Detector: catalog.Name(detector),
			Skill:    sin.Slug(),
			Sin:      sin.Name,
			File:     match.File(),
			Location: location,
			Scope:    match.Scope(),
			Twins:    twins,
			Custom:   custom.Owns(detector),
		})
	}

	return found
}

// groupsOf is, for each match, the locations of every match sharing its group key, itself included.
func groupsOf(detector detectors.Detector, matches []engine.Match) [][]string {
	grouped, isGrouped := detector.(detectors.Grouped)
	groups := make([][]string, len(matches))

	if !isGrouped {
		return groups
	}

	byKey := map[string][]string{}
	keys := make([]string, len(matches))
	keyed := make([]bool, len(matches))

	for i, match := range matches {
		keys[i], keyed[i] = grouped.GroupKey(match)

		if keyed[i] {
			byKey[keys[i]] = append(byKey[keys[i]], match.Location())
		}
	}

	for i := range matches {
		if keyed[i] {
			groups[i] = slices.Clone(byKey[keys[i]])
		}
	}

	return groups
}
