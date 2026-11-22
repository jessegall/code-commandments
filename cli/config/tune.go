package config

import (
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
)

func (c Config) Enabled(installed Installed) ([]rule.Detector, error) {
	var enabled []rule.Detector
	for _, detector := range Packaged(rule.All(), installed) {
		if !c.TurnsOff(detector) {
			enabled = append(enabled, detector)
		}
	}

	return c.Applied(enabled)
}

// Applied is each detector as the config tunes it: its layers, its public contracts, then the configure steps.
func (c Config) Applied(list []rule.Detector) ([]rule.Detector, error) {
	var kept []rule.Detector

	for _, detector := range list {
		if file, ok := detector.(rule.Rule); ok {
			var contracts, registrars []string
			layers := map[string][]string{}
			for _, engine := range file.Engines() {
				contracts = slices.AppendSeq(append(contracts, c.PublicContracts[engine]...), maps.Keys(c.ContractReasons[engine]))
				registrars = append(registrars, c.ContractRegistrars[engine]...)
				maps.Copy(layers, c.Layers(engine))
			}
			detector = file.WithLayers(layers).WithPublicContracts(contracts).WithContractRegistrars(registrars)
		}
		kept = append(kept, detector)
	}

	return tune(kept, c.Configurators)
}

// Installed says whether the project has a package.
type Installed func(sins.Package) bool

func InstalledIn(folder string) Installed {
	root := composerProjectOf(folder)

	return func(required sins.Package) bool {
		return required.InstalledIn(root)
	}
}

func composerProjectOf(folder string) string {
	for at := folder; ; at = filepath.Dir(at) {
		if _, err := os.Stat(filepath.Join(at, "composer.json")); err == nil {
			return at
		}
		if filepath.Dir(at) == at {
			return folder
		}
	}
}

func Everything(sins.Package) bool {
	return true
}

func Packaged(list []rule.Detector, installed Installed) []rule.Detector {
	var kept []rule.Detector

	for _, detector := range list {
		if required := detector.Sin().Definition().Requires; required.Name == "" || installed(required) {
			kept = append(kept, detector)
		}
	}

	return kept
}

func tune(list []rule.Detector, configurators []Configurator) ([]rule.Detector, error) {
	tuned := append([]rule.Detector(nil), list...)

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
func indexOf(list []rule.Detector, named Rule) int {
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
func apply(detector rule.Detector, call Call) (rule.Detector, error) {
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
