package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// reassembled is how many pieces of one object a call must hand a def before it could take the object whole.
const reassembled = 3

// DerivedArgumentDetector finds a parameter every call fills with a piece of an object it also hands over, or with
// one of three or more pieces of one object: the def should take the object and read the piece itself.
type DerivedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, DerivedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (DerivedArgumentDetector) Sin() sins.Sin {
	return pysins.DerivedArgument{}
}

// Find is every place the sin is committed.
func (DerivedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	redundant := map[string]map[string][]py.Node{}
	supplied := map[string]int{}
	unresolved := map[string]bool{}
	var slots []string
	for _, match := range py.In(codebase).WhereCall().Where(engine.As(py.Node.IsEvaluated)).Get() {
		call := py.Node{Match: match}
		target, resolved := program.TargetOf(call)
		bound, bindable := program.ArgumentsAt(call)
		if !resolved || !bindable {
			if callee := call.Callee(); callee.Kind() == "Attribute" {
				unresolved[callee.Name()] = true
			} else {
				unresolved[""] = true
			}
			continue
		}
		if buildsItsOwnClass(call, target) {
			continue
		}
		declared := py.DeclarationOf(target)
		for name := range bound {
			supplied[declared+"#"+name]++
		}
		for _, name := range redundantParameters(program, call, target, bound) {
			key := declared + "#" + name
			if redundant[key] == nil {
				redundant[key] = map[string][]py.Node{}
				slots = append(slots, key)
			}
			redundant[key][target.Name()] = append(redundant[key][target.Name()], call)
		}
	}
	var findings []engine.Match
	seen := map[py.Node]bool{}
	for _, key := range slots {
		for name, calls := range redundant[key] {
			if len(calls) != supplied[key] || unresolved[name] {
				continue
			}
			for _, call := range calls {
				if !seen[call] {
					seen[call] = true
					findings = append(findings, call.Match)
				}
			}
		}
	}

	return findings
}

// buildsItsOwnClass says whether the call is its own class's __init__, called from inside the class.
func buildsItsOwnClass(call, target py.Node) bool {
	class := call.EnclosingFunction().Parent()

	return target.Name() == "__init__" && class.Kind() == "ClassDef" && class.Initializer() == target
}

// redundantParameters is the parameters the call fills with a piece of an object it also hands whole, or with one
// of three or more pieces of one object the def could take whole.
func redundantParameters(program *py.Program, call, target py.Node, bound map[string]py.Node) []string {
	parameters := map[string]py.Node{}
	for _, parameter := range target.Parameters() {
		parameters[parameter.Name()] = parameter
	}
	for name := range bound {
		if parameters[name].TakesAnything() {
			return nil
		}
	}
	receiver := call.ReceiverName()
	whole := map[string]bool{}
	pieces := map[string]map[string]py.Node{}
	for name, argument := range bound {
		if argument.Kind() == "Name" && argument.Name() != receiver {
			whole[argument.Name()] = true
			continue
		}
		if root := argument.ProjectionRoot(); root != "" && root != receiver && parameters[name].IsScalarParameter() {
			if pieces[root] == nil {
				pieces[root] = map[string]py.Node{}
			}
			pieces[root][name] = argument
		}
	}
	var names []string
	for root, arguments := range pieces {
		if !whole[root] && (len(arguments) < reassembled || !couldTakeWhole(program, target, arguments, root)) {
			continue
		}
		for name := range arguments {
			names = append(names, name)
		}
	}

	return names
}

// couldTakeWhole says whether the def could take the object the pieces come from: mypy types it as a class of the
// program, and importing that class's module into the def's would close no cycle.
func couldTakeWhole(program *py.Program, target py.Node, pieces map[string]py.Node, root string) bool {
	for _, piece := range pieces {
		for _, part := range append([]py.Node{piece}, piece.Descendants()...) {
			if part.Kind() != "Name" || part.Name() != root {
				continue
			}
			class, ok := part.ResolvedClass()
			subject, held := program.ModuleOfClass(class)

			return ok && held && !program.WouldCloseACycle(program.ModuleOf(target), subject)
		}
	}

	return false
}
