// Package repent runs every scribe over a project: each engine's bridge serving the run, the chain of its scribes, swept to a fixed point.
package repent

import (
	"errors"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
	_ "github.com/jessegall/code-commandments/scribes/backend"
	_ "github.com/jessegall/code-commandments/scribes/frontend"
)

func Run(roots []string, scope scribes.Scope, filed scribes.Filed, given []detectors.Detector, only string) (scribes.Converged, error) {
	backend, unavailable, err := served(php.Here().Cached().Command, php.Over)
	if err != nil {
		return scribes.Converged{}, err
	}
	defer backend.Close()

	vue, vueUnavailable, err := served(frontend.Here().Cached().Command, frontend.Over)
	if err != nil {
		return scribes.Converged{}, err
	}
	defer vue.Close()

	unavailable = append(unavailable, vueUnavailable...)

	chain := Chain(backend, vue, given)
	if only != "" {
		chain.Matching(only)
	}

	converged := scribes.Converge(chain, roots, scope, scribes.Frozens{backend, vue}, filed)
	converged.Unavailable = unavailable

	return converged, nil
}

func served(command func() ([]string, error), read scribes.Reader) (*scribes.Scanner, []bridge.Unavailable, error) {
	scanner, err := serve(command, read)

	var missing bridge.Unavailable
	if errors.As(err, &missing) {
		return nil, []bridge.Unavailable{missing}, nil
	}

	return scanner, nil, err
}

func Chain(backend, vue *scribes.Scanner, given []detectors.Detector) *scribes.Chain {
	var steps []scribes.Step

	if backend != nil {
		steps = append(steps, scribes.MaintenanceSteps(catalog.PHP, backend)...)
		steps = append(steps, scribes.Steps(catalog.PHP, backend, given)...)
	}

	if vue != nil {
		steps = append(steps, scribes.Steps(catalog.Vue, vue, given)...)
		steps = append(steps, scribes.Steps(catalog.TypeScript, vue, given)...)
	}

	return scribes.Default(steps...)
}

func serve(command func() ([]string, error), read scribes.Reader) (*scribes.Scanner, error) {
	run, err := command()
	if err != nil {
		return nil, err
	}

	return scribes.Serve(run, read)
}
