package scribes

import "slices"

// Stage is when in a sweep a step runs.
type Stage int

const (
	Maintenance Stage = iota
	Fixing
	Extracting
	Normalising
)

// Staged is a step that says when it runs; a step that does not is a fixing step.
type Staged interface {
	Stage() Stage
}

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
