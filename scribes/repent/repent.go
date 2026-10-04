// Package repent runs every scribe over a project: each engine's bridge serving the run, the chain of its scribes,
// swept to a fixed point.
package repent

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
	_ "github.com/jessegall/code-commandments/scribes/backend"
	_ "github.com/jessegall/code-commandments/scribes/frontend"
)

// Run sweeps the chain for the detectors given over the roots, narrowed to the steps or sins only names when it
// names any, and returns what it settled on. It writes nothing.
func Run(roots []string, scope scribes.Scope, given []detectors.Detector, only string) (scribes.Converged, error) {
	backend, err := serve(php.Here().Command, php.Over)
	if err != nil {
		return scribes.Converged{}, err
	}
	defer backend.Close()
	vue, err := serve(frontend.Here().Cached().Command, frontend.Over)
	if err != nil {
		return scribes.Converged{}, err
	}
	defer vue.Close()

	chain := Chain(backend, vue, given)
	if only != "" {
		chain.Matching(only)
	}

	return scribes.Converge(chain, roots, scope, scribes.Frozens{backend, vue}), nil
}

// Chain is the chain repent runs over the two scanners: every maintainer, then a step for each repentable detector
// given, backend before frontend within each stage.
func Chain(backend, vue *scribes.Scanner, given []detectors.Detector) *scribes.Chain {
	var steps []scribes.Step
	steps = append(steps, scribes.MaintenanceSteps(catalog.Backend, backend)...)
	steps = append(steps, scribes.Steps(catalog.Backend, backend, given)...)
	steps = append(steps, scribes.Steps(catalog.Frontend, vue, given)...)

	return scribes.Default(steps...)
}

func serve(command func() ([]string, error), read scribes.Reader) (*scribes.Scanner, error) {
	run, err := command()
	if err != nil {
		return nil, err
	}

	return scribes.Serve(run, read)
}
