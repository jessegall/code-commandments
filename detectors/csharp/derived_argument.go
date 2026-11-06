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

func (DerivedArgumentDetector) Sin() sins.Sin {
	return cssins.DerivedArgument{}
}

func (d DerivedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// derivation is the record of a call handing its method pieces of an object it could take whole: the parameters it fills redundantly, each with the path of the piece it reads, and the name it calls the method by.
type derivation struct {
	slots  []string
	paths  []string
	called string
}

func (DerivedArgumentDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	program := cs.In(codebase).Program
	graph := cs.Namespaces(codebase)
	counts := map[string]int{}
	var order []string
	var candidates []detectors.Candidate
	for _, match := range cs.In(codebase).WhereCall().Get() {
		call := cs.Node{Match: match}
		if !call.Target().Exists() {
			candidates = append(candidates, detectors.Candidate{Record: unresolved{name: call.CalledName()}})
			continue
		}
		if !call.PassesByPosition() || !program.ReachesOwnSignature(call) || program.IsHandedOut(call.CalledName()) {
			continue
		}
		slot := call.Target().Symbol()
		arguments := call.Arguments()
		for position := range arguments {
			key := slot + "#" + strconv.Itoa(position)
			if counts[key] == 0 {
				order = append(order, key)
			}
			counts[key]++
		}
		derived := derivation{called: call.CalledName()}
		for _, position := range redundantPositions(call, program, graph) {
			derived.slots = append(derived.slots, slot+"#"+strconv.Itoa(position))
			derived.paths = append(derived.paths, readOff(arguments[position]))
		}
		if len(derived.slots) > 0 {
			candidates = append(candidates, detectors.Candidate{At: match, Record: derived})
		}
	}

	return append(candidates, supplied(counts, order)...)
}

func (DerivedArgumentDetector) Decide(candidates []detectors.Candidate) []int {
	counts, unresolved := suppliedIn(candidates)
	redundant := map[string][]int{}
	var order []string
	paths := map[string]map[string]bool{}
	for at, candidate := range candidates {
		derived, isDerived := candidate.Record.(derivation)
		if !isDerived {
			continue
		}
		for i, key := range derived.slots {
			if _, seen := redundant[key]; !seen {
				order = append(order, key)
				paths[key] = map[string]bool{}
			}
			redundant[key] = append(redundant[key], at)
			paths[key][derived.paths[i]] = true
		}
	}
	var findings []int
	for _, key := range order {
		calls := redundant[key]
		if len(calls) != counts[key] || len(paths[key]) != 1 || unresolved[candidates[calls[0]].Record.(derivation).called] {
			continue
		}
		for _, call := range calls {
			if !slices.Contains(findings, call) {
				findings = append(findings, call)
			}
		}
	}

	return findings
}

func readOff(piece cs.Node) string {
	return piece.ProjectionRoot().Type().Name() + piece.ProjectionPath()
}

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

func couldTakeWhole(call, root cs.Node, program *cs.Program, graph *cs.NamespaceGraph) bool {
	held := strings.TrimSuffix(root.Type().Name(), "?")

	return program.DeclaresType(held) && !graph.WouldCloseACycle(call.Target().Type(), held)
}

func (DerivedArgumentDetector) CrossFile() {}
