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

// weighed is a candidate an aggregating rule read in one unit: its record, and its finding when the verdict picks
// it, made while its unit was held.
type weighed struct {
	record  any
	finding engine.Finding
}

// judgeUnits judges the solution's C# a project at a time, so a solution too large to hold whole is still judged
// whole. Every project's summary is merged as the bridge's stream is cut into units; each unit is then read back
// alone, the merged summary its program, and judged; the rules that weigh candidates across the program decide
// over every unit's records once the last one is read. It answers how many C# files it judged.
func (c Command) judgeUnits(sources scan.Sources, rules []detectors.Detector, options options, progress *cli.Progress, console cli.Console) (Judgement, int, error) {
	var merged *cs.Summary
	units, err := sources.CSharpUnits(func(codebase *engine.Codebase) error {
		if summary := cs.Summarize(codebase); merged == nil {
			merged = summary
		} else {
			merged.Merge(summary)
		}

		return nil
	})
	if err != nil || units == nil {
		return Judgement{}, 0, err
	}
	defer units.Close()

	var ordinary []detectors.Detector
	var aggregating []detectors.Detector
	for _, rule := range rules {
		if _, weighs := rule.(detectors.Aggregating); weighs {
			aggregating = append(aggregating, rule)
		} else {
			ordinary = append(ordinary, rule)
		}
	}
	read := make([][]weighed, len(aggregating))
	var judgement Judgement
	files := 0
	for at := range units.Count() {
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
				held := weighed{record: candidate.Record}
				if candidate.At.Exists() {
					held.finding = findings(rule, []engine.Match{candidate.At})[0]
				}
				read[i] = append(read[i], held)
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
		for _, chosen := range rule.(detectors.Aggregating).Decide(records) {
			judgement.Findings = append(judgement.Findings, read[i][chosen].finding)
		}
	}
	judgement.Findings = Twinned(judgement.Findings)

	return judgement, files, nil
}

// candidatesOf is the rule's candidates in the codebase, or what it failed with.
func candidatesOf(rule detectors.Detector, codebase *engine.Codebase) (candidates []detectors.Candidate, failure any) {
	defer func() {
		if recovered := recover(); recovered != nil {
			failure = fmt.Sprintf("%v\n%s", recovered, debug.Stack())
		}
	}()

	return rule.(detectors.Aggregating).Candidates(codebase), nil
}

// joined is the names with the others added, each once.
func joined(names []string, others ...string) []string {
	for _, name := range others {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}
