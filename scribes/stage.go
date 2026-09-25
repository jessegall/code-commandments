package scribes

import "slices"

// Stage is when in a sweep a step runs.
type Stage int

const (
	// Maintenance steps regenerate what the code declares about itself, and run first.
	Maintenance Stage = iota
	// Fixing steps rewrite a sin in place.
	Fixing
	// Extracting steps lift markup out into components of its own, once the in-place fixes have run.
	Extracting
	// Normalising steps reshape what every other step has settled, and run last.
	Normalising
)

// Staged is a step that says when it runs; a step that does not is a fixing step.
type Staged interface {
	Stage() Stage
}

// Default is the chain repent runs: the steps by stage, each stage in the order the steps are given.
func Default(steps ...Step) *Chain {
	ordered := slices.Clone(steps)
	slices.SortStableFunc(ordered, func(a, b Step) int { return stageOf(a) - stageOf(b) })

	return &Chain{steps: ordered}
}

func stageOf(step Step) int {
	if staged, ok := step.(Staged); ok {
		return int(staged.Stage())
	}

	return int(Fixing)
}
