package judge

import (
	"fmt"
	"runtime/debug"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
)

// weighed is a candidate an aggregating rule read in one unit: its record, and its finding when the verdict picks it, made while its unit was held.
type weighed struct {
	record  any
	finding engine.Finding
}

func (c Command) judgeUnits(sources scan.Sources, rules []detectors.Detector, options options, progress *cli.Progress, console cli.Console) (Judgement, int, error) {
	var merged *cs.Summary
	var clean []bool
	set := newCSharpSet(sources.Checkout())
	units, err := sources.Units(func(codebase *engine.Codebase) error {
		set.add(codebase)
		clean = append(clean, !slices.ContainsFunc(rules, func(rule detectors.Detector) bool {
			_, weighs := rule.(detectors.Aggregating)

			return !weighs && !detectors.KnownClean(rule, codebase)
		}))

		if summary := cs.Summarize(codebase); merged == nil {
			merged = summary
		} else {
			merged.Merge(summary)
		}

		return nil
	})
	if err != nil {
		return Judgement{}, 0, err
	}
	if units == nil {
		return Judgement{Skipped: unreadUnits(sources)}, 0, nil
	}
	defer units.Close()

	var ordinary []detectors.Detector
	var aggregating []detectors.Detector
	var judgement Judgement
	for _, rule := range rules {
		if _, weighs := rule.(detectors.Aggregating); !weighs {
			ordinary = append(ordinary, rule)

			continue
		}

		if found, remembered := set.recall(rule); remembered {
			judgement.Findings = append(judgement.Findings, found...)

			continue
		}

		aggregating = append(aggregating, rule)
	}
	read := make([][]weighed, len(aggregating))
	files := 0
	for at := range units.Count() {
		if len(aggregating) == 0 && at < len(clean) && clean[at] {
			files += units.Files(at)
			engine.CountedRemembered(units.Files(at) * len(ordinary))

			continue
		}

		codebase, err := units.Load(at)
		if err != nil {
			return Judgement{}, 0, err
		}
		files += len(codebase.Files())
		cs.Judge(codebase, merged)
		tasks := make([]Task, len(ordinary))
		for i, rule := range ordinary {
			tasks[i] = Task{rule, codebase}
		}
		progress.Plan(len(tasks))
		part := Run(tasks, options.parallel, progress, console.Err)
		judgement.Findings = append(judgement.Findings, part.Findings...)
		judgement.Skipped = joined(judgement.Skipped, part.Skipped...)
		for i, rule := range aggregating {
			candidates, failure := candidatesOf(rule, codebase)
			if failure != nil {
				fmt.Fprintf(console.Err, "⚠ %s failed and was skipped — everything else still ran: %v\n", catalog.Name(rule), failure)
				judgement.Skipped = joined(judgement.Skipped, catalog.Name(rule))

				continue
			}
			for _, candidate := range candidates {
				read[i] = append(read[i], weighedOf(rule, candidate))
			}
		}
	}
	progress.Finish()

	for i, rule := range aggregating {
		if slices.Contains(judgement.Skipped, catalog.Name(rule)) {
			continue
		}
		records := make([]detectors.Candidate, len(read[i]))
		for at, held := range read[i] {
			records[at] = detectors.Candidate{Record: held.record}
		}
		var found []engine.Finding
		for _, chosen := range rule.(detectors.Aggregating).Decide(records) {
			found = append(found, read[i][chosen].finding)
		}
		set.keep(rule, found)
		judgement.Findings = append(judgement.Findings, found...)
	}
	judgement.Findings = Twinned(judgement.Findings)
	if files == 0 {
		judgement.Skipped = joined(judgement.Skipped, unreadUnits(sources)...)
	}

	return judgement, files, nil
}

// unreadUnits names each bridge that reads in units and had files to read but read none.
func unreadUnits(sources scan.Sources) []string {
	var unread []string
	for _, held := range scan.Bridges {
		if held.InUnits && sources.Count(held.Languages...) > 0 {
			unread = append(unread, "the "+held.Label+" bridge")
		}
	}

	return unread
}

func weighedOf(rule detectors.Detector, candidate detectors.Candidate) weighed {
	held := weighed{record: candidate.Record}
	if candidate.At.Exists() {
		held.finding = findings(rule, []engine.Match{candidate.At})[0]
	}

	return held
}

func candidatesOf(rule detectors.Detector, codebase *engine.Codebase) (candidates []detectors.Candidate, failure any) {
	defer func() {
		if recovered := recover(); recovered != nil {
			failure = fmt.Sprintf("%v\n%s", recovered, debug.Stack())
		}
	}()

	return rule.(detectors.Aggregating).Candidates(codebase), nil
}

func joined(names []string, others ...string) []string {
	for _, name := range others {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}
