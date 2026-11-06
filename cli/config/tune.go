package config

import (
	"fmt"
	"reflect"
	"slices"
	"unicode"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/sins"
)

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

func rulesOf(detector detectors.Detector) []Rule {
	engine, _ := detectors.EngineOf(detector)
	sin := detector.Sin()

	return []Rule{
		{Kind: Detector, Engine: engine, Name: catalog.Name(detector)},
		{Kind: Sin, Engine: engine, Name: catalog.Name(sin)},
		{Kind: Skill, Engine: engine, Name: catalog.Name(sin.Definition().Skill)},
	}
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

func indexOf(list []detectors.Detector, rule Rule) int {
	for i, detector := range list {
		engine, _ := detectors.EngineOf(detector)

		if rule.Kind == Detector && rule.Engine == engine && rule.Name == catalog.Name(detector) {
			return i
		}
	}

	return -1
}

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
