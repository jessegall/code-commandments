package python

import (
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// convertedShare is the share of a parameter's callers that must convert what they hand it the same way.
const convertedShare = 0.5

// ConvertedArgumentDetector finds a scalar parameter most of whose callers build it by one and the same conversion:
// the parameter should take what they convert.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConvertedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (ConvertedArgumentDetector) Sin() sins.Sin {
	return pysins.ConvertedArgument{}
}

// Find is every place the sin is committed.
func (ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	calls := py.In(codebase).WhereCall().Where(engine.As(py.Node.IsEvaluated)).Get()
	supplied := map[string]int{}
	for _, match := range calls {
		call := py.Node{Match: match}
		for name := range scalarArguments(program, call) {
			supplied[slot(program, call, name)]++
		}
	}
	var findings []engine.Match
	for _, bucket := range engine.RecurringBuckets(calls, func(match engine.Match) (string, bool) { return conversionKey(program, py.Node{Match: match}) }, 2) {
		key, _ := conversionKey(program, py.Node{Match: bucket[0]})
		if float64(len(bucket))/float64(supplied[strings.SplitN(key, "=", 2)[0]]) >= convertedShare {
			findings = append(findings, bucket...)
		}
	}

	return findings
}

// conversionKey is the call's first scalar parameter it fills through a conversion other than to its own class,
// with that conversion: `declaration#parameter=Class.method`.
func conversionKey(program *py.Program, call py.Node) (string, bool) {
	for name, argument := range orderedArguments(program, call) {
		if conversion, ok := call.ConversionIn(argument); ok && !isCallersOwn(call, conversion) {
			return slot(program, call, name) + "=" + conversion, true
		}
	}

	return "", false
}

// orderedArguments is the call's scalar arguments in the order the call writes them.
func orderedArguments(program *py.Program, call py.Node) func(func(string, py.Node) bool) {
	return func(yield func(string, py.Node) bool) {
		bound := scalarArguments(program, call)
		names := slices.Collect(maps.Keys(bound))
		slices.SortFunc(names, func(a, b string) int { return bound[a].Node().Span.Start - bound[b].Node().Span.Start })
		for _, name := range names {
			if !yield(name, bound[name]) {
				return
			}
		}
	}
}

// scalarArguments is what the call hands each scalar parameter of the def it reaches.
func scalarArguments(program *py.Program, call py.Node) map[string]py.Node {
	target, resolved := program.TargetOf(call)
	bound, bindable := program.ArgumentsAt(call)
	if !resolved || !bindable {
		return nil
	}
	scalars := map[string]py.Node{}
	for _, parameter := range target.Parameters() {
		if argument, ok := bound[parameter.Name()]; ok && parameter.IsScalarParameter() {
			scalars[parameter.Name()] = argument
		}
	}

	return scalars
}

// slot is where a parameter of the call's target is declared: `declaration#parameter`.
func slot(program *py.Program, call py.Node, name string) string {
	target, _ := program.TargetOf(call)

	return py.DeclarationOf(target) + "#" + name
}

// isCallersOwn says whether the conversion belongs to the calling class itself, or a class nested in it.
func isCallersOwn(call py.Node, conversion string) bool {
	class := call.EnclosingFunction().Parent()
	if class.Kind() != "ClassDef" {
		return false
	}
	module := class.Node().Symbol[:strings.LastIndex(class.Node().Symbol, ".")]

	return strings.HasPrefix(conversion+".", module+"."+class.Name()+".")
}
