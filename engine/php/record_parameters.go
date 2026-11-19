package php

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// RecordParameters is every parameter of the program a call fills with a string-keyed record: a literal handed to it, a local holding one, or another such parameter passed on.
type RecordParameters struct {
	filled map[*contract.Node]bool
	shapes map[*contract.Node][][]string
}

type filling struct {
	argument engine.Match
	keys     []string
}

var recordParameters = engine.Memoised(readRecordParameters)

func RecordParametersOf(codebase *engine.Codebase) *RecordParameters {
	return recordParameters.Of(codebase)
}

func readRecordParameters(codebase *engine.Codebase) *RecordParameters {
	records := &RecordParameters{filled: map[*contract.Node]bool{}, shapes: map[*contract.Node][][]string{}}
	var carried []filling
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if literal := (Node{Match: file.Match(node.ID)}); node.Kind == "Expr_Array" && literal.IsStringKeyedRecord() {
				carried = append(carried, fillings(carriers(literal.Match), literal.StringKeys())...)
			}
		}
	}
	for len(carried) > 0 {
		next := carried[0]
		carried = carried[1:]
		parameter := ParameterFilled(codebase, next.argument)
		if !parameter.Exists() || next.argument.IsTestReaching(parameter) {
			continue
		}
		records.shapes[parameter.Node()] = append(records.shapes[parameter.Node()], next.keys)
		if records.filled[parameter.Node()] {
			continue
		}
		records.filled[parameter.Node()] = true
		carried = append(carried, fillings(passedOn(parameter.Child("var")), next.keys)...)
	}

	return records
}

func (r *RecordParameters) Holds(parameter engine.Match) bool {
	return r.filled[parameter.Node()] && shareAKey(r.shapes[parameter.Node()])
}

func fillings(arguments []engine.Match, keys []string) []filling {
	var filled []filling
	for _, argument := range arguments {
		filled = append(filled, filling{argument: argument, keys: keys})
	}

	return filled
}

func shareAKey(shapes [][]string) bool {
	common := shapes[0]
	for _, shape := range shapes[1:] {
		common = slices.DeleteFunc(slices.Clone(common), func(key string) bool { return !slices.Contains(shape, key) })
	}

	return len(common) > 0
}

func carriers(literal engine.Match) []engine.Match {
	holder := literal.Parent()
	if holder.Kind() == "Arg" {
		return []engine.Match{holder}
	}
	if holder.Kind() == "Expr_Assign" && literal.Node().Field == "expr" {
		return passedOn(holder.Child("var"))
	}

	return nil
}

func passedOn(variable engine.Match) []engine.Match {
	var arguments []engine.Match
	for _, interaction := range Trace(variable) {
		if interaction.Kind == Argument {
			arguments = append(arguments, interaction.Node.Parent())
		}
	}

	return arguments
}
