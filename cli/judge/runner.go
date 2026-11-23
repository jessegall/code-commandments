// Package judge is the `judge` command: it scans a tree, runs every enabled detector over it, and reports the sins grouped by the skill that fixes each, into the console and a checklist.
package judge

import (
	"fmt"
	"io"
	"os"
	"runtime"
	"runtime/debug"
	"slices"
	"sync"

	"github.com/jessegall/code-commandments/cli/custom"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/sidebyside"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rule"
)

// Judgement is what a run found, and the rules that broke and could not run.
type Judgement struct {
	Findings []engine.Finding
	Skipped  []string
}

// Task is one detector and the codebase it judges.
type Task struct {
	Detector rule.Detector
	Codebase *engine.Codebase
}

func Run(tasks []Task, parallel int, progress *cli.Progress, warn io.Writer) Judgement {
	results := make([]attempt, len(tasks))
	var drawing sync.Mutex

	progress.Start()

	sidebyside.Each(len(tasks), min(parallel, runtime.NumCPU()), func(index int) {
		results[index] = try(tasks[index], warn)

		drawing.Lock()
		progress.Advance("")
		drawing.Unlock()
	})

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
		if stopped, wasStopped := failure.(rule.WentOverAFileAgain); wasStopped {
			fmt.Fprintf(warn, "⚠ %s was stopped and skipped — everything else still ran: %s\n", name, stopped.Error())
		} else {
			fmt.Fprintf(warn, "⚠ %s failed and was skipped — everything else still ran: %v\n%s\n", name, failure, debug.Stack())
		}
		warning.Unlock()

		result = attempt{skipped: name}
	}()

	matches := rule.FindRemembering(task.Detector, task.Codebase)

	return attempt{findings: findings(task.Detector, matches)}
}

func findings(detector rule.Detector, matches []engine.Match) []engine.Finding {
	sin := detector.Sin().Definition()
	language, _ := rule.EngineOf(detector)
	groups := groupsOf(detector, matches)
	found := make([]engine.Finding, 0, len(matches))

	for i, match := range matches {
		if !onDisk(match.File()) {
			continue
		}

		location := match.Location()
		var twins []string

		for _, member := range groups[i] {
			if member != location {
				twins = append(twins, member)
			}
		}

		group := ""
		if grouped, isGrouped := detector.(rule.Grouped); isGrouped {
			if key, keyed := grouped.GroupKey(match); keyed {
				group = key
			}
		}
		advice := ""
		if advises, ok := detector.(rule.AdvisingDetector); ok {
			advice = advises.AdviceFor(match)
		}

		found = append(found, engine.Finding{
			Group:    group,
			Detector: catalog.Name(detector),
			Engine:   string(language),
			Skill:    sin.Slug(),
			Sin:      sin.Name,
			File:     match.File(),
			Line:     match.Line(),
			Location: location,
			Scope:    match.Scope(),
			Twins:    twins,
			Custom:   custom.Owns(detector),
			Advice:   advice,
		})
	}

	return found
}

func Twinned(findings []engine.Finding) []engine.Finding {
	type group struct{ detector, key string }
	members := map[group][]string{}
	for _, finding := range findings {
		if finding.Group != "" {
			members[group{finding.Detector, finding.Group}] = append(members[group{finding.Detector, finding.Group}], finding.Location)
		}
	}
	for at, finding := range findings {
		if finding.Group == "" {
			continue
		}
		findings[at].Twins = nil
		for _, member := range members[group{finding.Detector, finding.Group}] {
			if member != finding.Location {
				findings[at].Twins = append(findings[at].Twins, member)
			}
		}
	}

	return findings
}

func groupsOf(detector rule.Detector, matches []engine.Match) [][]string {
	grouped, isGrouped := detector.(rule.Grouped)
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

func onDisk(path string) bool {
	if path == "" {
		return true
	}

	info, err := os.Stat(path)

	return err == nil && info.Mode().IsRegular()
}
