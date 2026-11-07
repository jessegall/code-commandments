package scribes

import (
	"fmt"
	"github.com/jessegall/code-commandments/bridge"
	"os"
)

const MaxSweeps = 10

// Frozen says whether a file is read but never rewritten.
type Frozen interface {
	IsFrozen(path string) bool
}

// Filed says whether a rule's finding at a place has been reported to the tool's own repository as wrong.
type Filed interface {
	IsFiled(rule, file string, line int) bool
}

// NothingFiled is the answer for a run that knows of no report, which is every run of a project that has filed none.
type NothingFiled struct{}

func (NothingFiled) IsFiled(string, string, int) bool {
	return false
}

// Converged is what a run of the chain settled on: every file's final content, the steps that broke, and whether the chain settled before MaxSweeps.
type Converged struct {
	Files       Rewrites
	Skipped     []Skipped
	Settled     bool
	Unavailable []bridge.Unavailable
}

// Skipped is a step that broke and was dropped, and why.
type Skipped struct {
	Step string
	Err  error
}

func Converge(chain *Chain, roots []string, scope Scope, frozen Frozen, filed Filed) Converged {
	steps := chain.Steps()
	drafts := Rewrites{}
	var skipped []Skipped

	for sweep := 0; sweep < MaxSweeps; sweep++ {
		before := drafts.clone()
		for index := 0; index < len(steps); index++ {
			step := steps[index]
			rewrites, err := attempt(step, Pass{Roots: roots, Scope: scope, Frozen: frozen, Filed: filed, Drafts: drafts})
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

func attempt(step Step, pass Pass) (rewrites Rewrites, err error) {
	defer func() {
		if failure := recover(); failure != nil {
			err = fmt.Errorf("%v", failure)
		}
	}()

	return step.Run(pass)
}

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
