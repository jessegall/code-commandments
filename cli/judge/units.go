package judge

import (
	"slices"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

func (c Command) judgeUnits(sources scan.Sources, rules []detectors.Detector, options options, progress *cli.Progress, console cli.Console) (Judgement, int, error) {
	var clean []bool
	units, err := sources.Units(func(codebase *engine.Codebase) error {
		clean = append(clean, !slices.ContainsFunc(rules, func(rule detectors.Detector) bool { return !detectors.KnownClean(rule, codebase) }))

		return nil
	})
	if err != nil {
		return Judgement{}, 0, err
	}
	if units == nil {
		return Judgement{Skipped: unreadUnits(sources)}, 0, nil
	}
	defer units.Close()

	var judgement Judgement
	files := 0
	judge := func(codebase *engine.Codebase) {
		files += len(codebase.Files())
		tasks := make([]Task, len(rules))
		for i, rule := range rules {
			tasks[i] = Task{rule, codebase}
		}
		progress.Plan(len(tasks))
		part := Run(tasks, options.parallel, progress, console.Err)
		judgement.Findings = append(judgement.Findings, part.Findings...)
		judgement.Skipped = joined(judgement.Skipped, part.Skipped...)
	}
	switch {
	case !slices.Contains(clean, false):
		for at := range units.Count() {
			files += units.Files(at)
			engine.CountedRemembered(units.Files(at) * len(rules))
		}
	case units.FitWhole():
		whole, err := units.LoadWhole()
		if err != nil {
			return Judgement{}, 0, err
		}
		judge(whole)
	default:
		for at := range units.Count() {
			if at < len(clean) && clean[at] {
				files += units.Files(at)
				engine.CountedRemembered(units.Files(at) * len(rules))

				continue
			}
			codebase, err := units.Load(at)
			if err != nil {
				return Judgement{}, 0, err
			}
			judge(codebase)
		}
	}
	progress.Finish()

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

func joined(names []string, others ...string) []string {
	for _, name := range others {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}
