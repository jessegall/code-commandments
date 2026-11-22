package config

import (
	"fmt"
	"maps"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
)

type publicContractDetector interface {
	detectors.Detector
	WithPublicContracts([]string, []string) detectors.Detector
}

type layeredDetector interface {
	WithLayers(map[string][]string) detectors.Detector
}

func (c Config) Enabled(installed Installed) ([]detectors.Detector, error) {
	var enabled []detectors.Detector
	for _, detector := range Packaged(detectors.All(), installed) {
		if !c.TurnsOff(detector) {
			enabled = append(enabled, detector)
		}
	}

	return c.Applied(enabled)
}

// Applied is each detector as the config tunes it: its layers, its public contracts, then the configure steps.
func (c Config) Applied(list []detectors.Detector) ([]detectors.Detector, error) {
	var kept []detectors.Detector

	for _, detector := range list {
		if marks, ok := detector.(layeredDetector); ok {
			engine, _ := detectors.EngineOf(detector)
			detector = marks.WithLayers(c.Layers(engine))
		}
		if file, ok := detector.(rule.Rule); ok {
			engine := file.Engine()
			contracts := slices.AppendSeq(slices.Clone(c.PublicContracts[engine]), maps.Keys(c.ContractReasons[engine]))
			detector = file.WithLayers(c.Layers(engine)).WithPublicContracts(contracts)
		}
		if accepts, ok := detector.(publicContractDetector); ok {
			engine, _ := detectors.EngineOf(detector)
			contracts := slices.AppendSeq(slices.Clone(c.PublicContracts[engine]), maps.Keys(c.ContractReasons[engine]))
			if registrars := c.ContractRegistrars[engine]; len(contracts) > 0 || len(registrars) > 0 {
				detector = accepts.WithPublicContracts(contracts, registrars)
			}
		}
		kept = append(kept, detector)
	}

	return tune(kept, c.Configurators)
}

// Installed says whether the project has a package.
type Installed func(sins.Package) bool

func InstalledIn(root string) Installed {
	return func(required sins.Package) bool {
		return required.InstalledIn(root)
	}
}

func Everything(sins.Package) bool {
	return true
}

func Packaged(list []detectors.Detector, installed Installed) []detectors.Detector {
	var kept []detectors.Detector

	for _, detector := range list {
		if required := detector.Sin().Definition().Requires; required.Name == "" || installed(required) {
			kept = append(kept, detector)
		}
	}

	return kept
}

func tune(list []detectors.Detector, configurators []Configurator) ([]detectors.Detector, error) {
	tuned := append([]detectors.Detector(nil), list...)

	for _, configurator := range configurators {
		calls := slices.DeleteFunc(slices.Clone(configurator.Calls), func(call Call) bool { return call.Method == Judges })

		if len(calls) == 0 {
			continue
		}

		at := indexOf(tuned, configurator.Target)

		if at < 0 {
			return nil, &cli.InvalidConfiguration{Reason: "configure(" + configurator.Target.ID() + "): that detector is not registered, or was disabled."}
		}

		for _, call := range calls {
			detector, err := apply(tuned[at], call)
			if err != nil {
				return nil, &cli.InvalidConfiguration{Reason: "configure(" + configurator.Target.ID() + "): " + err.Error()}
			}

			tuned[at] = detector
		}
	}

	return tuned, nil
}

// indexOf is where the rule a configure names stands in the list: by its name, its sin's name or an alias, under any
// engine it judges, or none written.
func indexOf(list []detectors.Detector, named Rule) int {
	for i, detector := range list {
		if named.Kind == Detector && named.Names(detector) {
			return i
		}
	}

	return -1
}

// parameterised is a rule whose file declares parameters, which configure sets by name.
type parameterised interface {
	WithParameter(name string, value any) (rule.Rule, error)
}

// apply sets one parameter of a rule: configure's {"name": [value]}. A layer step is read once for the whole engine
// and reaches every rule through WithLayers, so it is left to that.
func apply(detector detectors.Detector, call Call) (detectors.Detector, error) {
	declaring, declares := detector.(parameterised)
	switch {
	case call.Method == "layer":
		return detector, nil
	case !declares:
		return nil, fmt.Errorf("%s declares no parameters to set; only a rule file declares them", catalog.Name(detector))
	case len(call.Args) != 1:
		return nil, fmt.Errorf("the parameter %s is set to one value, written [value], and %d are written", call.Method, len(call.Args))
	}

	return declaring.WithParameter(call.Method, call.Args[0].Value)
}
