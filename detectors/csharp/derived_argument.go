package csharp

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DerivedArgumentDetector finds arguments a method of the codebase's own is always handed as pieces read off an object the call also holds: the method should take the object.
type DerivedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DerivedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (DerivedArgumentDetector) Sin() sins.Sin {
	return cssins.DerivedArgument{}
}

// Find is every place the sin is committed.
func (DerivedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program
	graph := cs.Namespaces(codebase)
	redundant := map[string][]engine.Match{}
	var order []string
	supplied := map[string]int{}
	paths := map[string]map[string]bool{}
	unresolved := map[string]bool{}
	for _, match := range cs.In(codebase).WhereCall().Get() {
		call := cs.Node{Match: match}
		if !call.Target().Exists() {
			unresolved[call.CalledName()] = true
			continue
		}
		if !call.PassesByPosition() || !program.ReachesOwnSignature(call) || program.IsHandedOut(call.CalledName()) {
			continue
		}
		slot := call.Target().Symbol()
		arguments := call.Arguments()
		for position := range arguments {
			supplied[slot+"#"+strconv.Itoa(position)]++
		}
		for _, position := range redundantPositions(call, program, graph) {
			key := slot + "#" + strconv.Itoa(position)
			if _, seen := redundant[key]; !seen {
				order = append(order, key)
				paths[key] = map[string]bool{}
			}
			redundant[key] = append(redundant[key], match)
			paths[key][arguments[position].ProjectionPath()] = true
		}
	}
	var findings []engine.Match
	for _, key := range order {
		calls := redundant[key]
		if len(calls) != supplied[key] || len(paths[key]) != 1 || unresolved[cs.Node{Match: calls[0]}.CalledName()] {
			continue
		}
		for _, call := range calls {
			if !slices.ContainsFunc(findings, func(found engine.Match) bool { return found.Node() == call.Node() }) {
				findings = append(findings, call)
			}
		}
	}

	return findings
}

// redundantPositions is the positions of the call's arguments that are pieces read off an object the call is also
// handed whole, or three or more pieces of one object the method could take whole instead.
func redundantPositions(call cs.Node, program *cs.Program, graph *cs.NamespaceGraph) []int {
	if slices.Contains(call.Target().Parameters(), "global::System.Object") {
		return nil
	}
	receiver := call.ReceiverName()
	whole := map[string]bool{}
	pieces := map[string][]int{}
	roots := map[string]cs.Node{}
	var names []string
	for position, argument := range call.Arguments() {
		if argument.Is("IdentifierName") && argument.Name() != receiver {
			whole[argument.Name()] = true
			continue
		}
		root := argument.ProjectionRoot()
		if root.Exists() && root.Name() != receiver && call.FillsScalarAt(position) {
			if _, seen := pieces[root.Name()]; !seen {
				names = append(names, root.Name())
				roots[root.Name()] = root
			}
			pieces[root.Name()] = append(pieces[root.Name()], position)
		}
	}
	var positions []int
	for _, name := range names {
		if whole[name] || (len(pieces[name]) >= 3 && couldTakeWhole(call, roots[name], program, graph)) {
			positions = append(positions, pieces[name]...)
		}
	}

	return positions
}

// couldTakeWhole says whether the method could be handed the object the pieces come from: a type the codebase
// declares, whose namespace it may reach without closing a cycle.
func couldTakeWhole(call, root cs.Node, program *cs.Program, graph *cs.NamespaceGraph) bool {
	held := strings.TrimSuffix(root.Type().Name(), "?")

	return program.DeclaresType(held) && !graph.WouldCloseACycle(call.Target().Type(), held)
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (DerivedArgumentDetector) WholeTree() {}
