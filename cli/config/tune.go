package config

import (
	"fmt"
	"reflect"
	"unicode"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/sins"
)

// Enabled are the shipped detectors the project keeps: every one whose package the project has, that the
// config does not disable by itself, its sin or its skill, tuned as the config configures it.
func (c Config) Enabled(installed Installed) ([]detectors.Detector, error) {
	var kept []detectors.Detector

	for _, detector := range Packaged(detectors.All(), installed) {
		if !c.Disables(rulesOf(detector)...) {
			kept = append(kept, detector)
		}
	}

	return tune(kept, c.Configurators)
}

// Installed says whether the project has a package.
type Installed func(sins.Package) bool

// InstalledIn is what the project at root has installed.
func InstalledIn(root string) Installed {
	return func(required sins.Package) bool {
		return required.InstalledIn(root)
	}
}

// Everything takes every package as installed, to keep package-bound rules on any project.
func Everything(sins.Package) bool {
	return true
}

// Packaged are the detectors whose sin holds in the project: it requires no package, or one installed.
func Packaged(list []detectors.Detector, installed Installed) []detectors.Detector {
	var kept []detectors.Detector

	for _, detector := range list {
		if required := detector.Sin().Definition().Requires; required.Name == "" || installed(required) {
			kept = append(kept, detector)
		}
	}

	return kept
}

// rulesOf names the detector, its sin and its skill as a config names them.
func rulesOf(detector detectors.Detector) []Rule {
	engine, _ := detectors.EngineOf(detector)
	sin := detector.Sin()

	return []Rule{
		{Kind: Detector, Engine: engine, Name: catalog.Name(detector)},
		{Kind: Sin, Engine: engine, Name: catalog.Name(sin)},
		{Kind: Skill, Engine: engine, Name: catalog.Name(sin.Definition().Skill)},
	}
}

// tune applies each configurator to the detector it names: every call it makes becomes the detector's
// method of the same name, and a method that answers a detector replaces it. A configurator naming a
// detector not in the list is an invalid configuration.
func tune(list []detectors.Detector, configurators []Configurator) ([]detectors.Detector, error) {
	tuned := append([]detectors.Detector(nil), list...)

	for _, configurator := range configurators {
		at := indexOf(tuned, configurator.Target)

		if at < 0 {
			return nil, &cli.InvalidConfiguration{Reason: "configure(" + configurator.Target.ID() + "): that detector is not registered, or was disabled."}
		}

		for _, call := range configurator.Calls {
			detector, err := apply(tuned[at], call)
			if err != nil {
				return nil, &cli.InvalidConfiguration{Reason: "configure(" + configurator.Target.ID() + "): " + err.Error()}
			}

			tuned[at] = detector
		}
	}

	return tuned, nil
}

func indexOf(list []detectors.Detector, rule Rule) int {
	for i, detector := range list {
		engine, _ := detectors.EngineOf(detector)

		if rule.Kind == Detector && rule.Engine == engine && rule.Name == catalog.Name(detector) {
			return i
		}
	}

	return -1
}

// apply calls the method a configurator names on the detector.
func apply(detector detectors.Detector, call Call) (detectors.Detector, error) {
	method := reflect.ValueOf(detector).MethodByName(exported(call.Method))

	if !method.IsValid() {
		return nil, fmt.Errorf("%s has no %s()", catalog.Name(detector), call.Method)
	}

	args, err := arguments(method.Type(), call.Args)
	if err != nil {
		return nil, fmt.Errorf("%s(): %w", call.Method, err)
	}

	answered := method.Call(args)

	if len(answered) == 1 {
		if next, isDetector := answered[0].Interface().(detectors.Detector); isDetector {
			return next, nil
		}
	}

	return detector, nil
}

// arguments converts the literal arguments, in order, to the method's parameters; the last one takes the
// rest when the method is variadic, and an array given for it is spread.
func arguments(method reflect.Type, given []Arg) ([]reflect.Value, error) {
	var args []reflect.Value

	for i, arg := range given {
		parameter, spread := parameterAt(method, i)
		if parameter == nil {
			return nil, fmt.Errorf("takes %d arguments, given %d", method.NumIn(), len(given))
		}

		items, isArray := arg.Value.([]any)

		if spread && isArray {
			for _, item := range items {
				value, err := convert(item, parameter)
				if err != nil {
					return nil, err
				}

				args = append(args, value)
			}

			continue
		}

		value, err := convert(arg.Value, parameter)
		if err != nil {
			return nil, err
		}

		args = append(args, value)
	}

	return args, nil
}

// parameterAt is the type the argument at i fills, and whether it is the variadic rest.
func parameterAt(method reflect.Type, i int) (reflect.Type, bool) {
	last := method.NumIn() - 1

	switch {
	case method.IsVariadic() && i >= last:
		return method.In(last).Elem(), true
	case i <= last:
		return method.In(i), false
	default:
		return nil, false
	}
}

func convert(value any, to reflect.Type) (reflect.Value, error) {
	if to.Kind() == reflect.Slice {
		items, isArray := value.([]any)
		if !isArray {
			return reflect.Value{}, fmt.Errorf("wants a list, given %v", value)
		}

		slice := reflect.MakeSlice(to, 0, len(items))

		for _, item := range items {
			each, err := convert(item, to.Elem())
			if err != nil {
				return reflect.Value{}, err
			}

			slice = reflect.Append(slice, each)
		}

		return slice, nil
	}

	given := reflect.ValueOf(value)

	if !given.IsValid() || !given.Type().ConvertibleTo(to) || given.Kind() != to.Kind() {
		return reflect.Value{}, fmt.Errorf("wants a %s, given %v", to.Kind(), value)
	}

	return given.Convert(to), nil
}

func exported(method string) string {
	if method == "" {
		return method
	}

	runes := []rune(method)
	runes[0] = unicode.ToUpper(runes[0])

	return string(runes)
}
