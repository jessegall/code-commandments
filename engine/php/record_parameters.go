package php

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// RecordParameters is every parameter of the program a call fills with a string-keyed record: a literal handed to it, a local holding one, or another such parameter passed on.
type RecordParameters struct {
	filled map[*contract.Node]bool
}

var recordParameters = engine.Memoised(readRecordParameters)

func RecordParametersOf(codebase *engine.Codebase) *RecordParameters {
	return recordParameters.Of(codebase)
}

func readRecordParameters(codebase *engine.Codebase) *RecordParameters {
	records := &RecordParameters{filled: map[*contract.Node]bool{}}
	var carried []engine.Match
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if literal := file.Match(node.ID); node.Kind == "Expr_Array" && (Node{Match: literal}).IsStringKeyedRecord() {
				carried = append(carried, carriers(literal)...)
			}
		}
	}
	for len(carried) > 0 {
		argument := carried[0]
		carried = carried[1:]
		parameter := ParameterFilled(codebase, argument)
		if !parameter.Exists() || records.filled[parameter.Node()] {
			continue
		}
		records.filled[parameter.Node()] = true
		carried = append(carried, passedOn(parameter.Child("var"))...)
	}

	return records
}

func (r *RecordParameters) Holds(parameter engine.Match) bool {
	return r.filled[parameter.Node()]
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
