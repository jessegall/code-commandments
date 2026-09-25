package scribes

import (
	"fmt"
	"os"
)

// MaxSweeps is how many times the chain runs before repent applies what it has, settled or not.
const MaxSweeps = 10

// Frozen says whether a file is read but never rewritten.
type Frozen interface {
	IsFrozen(path string) bool
}

// Converged is what a run of the chain settled on: every file's final content, the steps that broke, and whether
// the chain settled before MaxSweeps.
type Converged struct {
	Files   Rewrites
	Skipped []Skipped
	Settled bool
}

// Skipped is a step that broke and was dropped, and why.
type Skipped struct {
	Step string
	Err  error
}

// Converge runs the chain over the roots until a sweep changes nothing. A step that breaks is dropped and named;
// a step's rewrite is kept only when every existing file it touches is in scope and not frozen, so a run never
// half-applies a fix across files.
func Converge(chain *Chain, roots []string, scope Scope, frozen Frozen) Converged {
	steps := chain.Steps()
	drafts := Rewrites{}
	var skipped []Skipped

	for sweep := 0; sweep < MaxSweeps; sweep++ {
		before := drafts.clone()
		for index := 0; index < len(steps); index++ {
			step := steps[index]
			rewrites, err := attempt(step, Pass{Roots: roots, Scope: scope, Frozen: frozen, Drafts: drafts})
			if err != nil {
				skipped = append(skipped, Skipped{Step: step.Name(), Err: err})
				steps = append(steps[:index], steps[index+1:]...)
				index--

				continue
			}
			if permits(rewrites, scope, frozen) {
				drafts = drafts.with(rewrites)
			}
		}
		if drafts.Equal(before) {
			return Converged{Files: drafts, Skipped: skipped, Settled: true}
		}
	}

	return Converged{Files: drafts, Skipped: skipped}
}

// attempt runs one step, a panic inside it read as the step breaking.
func attempt(step Step, pass Pass) (rewrites Rewrites, err error) {
	defer func() {
		if failure := recover(); failure != nil {
			err = fmt.Errorf("%v", failure)
		}
	}()

	return step.Run(pass)
}

// permits says whether every file the rewrites touch that exists on disk is one the run may fix.
func permits(rewrites Rewrites, scope Scope, frozen Frozen) bool {
	for _, path := range rewrites.paths {
		if _, err := os.Stat(path); err != nil {
			continue
		}
		if !scope.Includes(path) || frozen.IsFrozen(path) {
			return false
		}
	}

	return true
}
