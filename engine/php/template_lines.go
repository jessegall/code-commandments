package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func joinedLines(call engine.Match) []engine.Match {
	if !CallsGlobalFunction(call, "implode") && !CallsGlobalFunction(call, "join") {
		return nil
	}
	join := Node{Match: call}
	if !join.Argument(0).IsNewlineSeparator() {
		return nil
	}
	argument := join.Argument(1).Match
	if argument.Kind() == "Expr_Array" {
		return arrayLineValues(argument)
	}
	writes := Trace(argument)
	function := enclosingFunction(argument)
	var initializer engine.Match
	for _, write := range writes {
		if !write.IsWrite() {
			continue
		}
		assignment := write.Node.Parent()
		if assignment.EndsBefore(argument) && enclosingFunction(assignment) == function {
			initializer = assignment
		}
	}
	if !initializer.Exists() || !branchContainsRead(initializer, argument) || initializer.Child("expr").Kind() != "Expr_Array" {
		return nil
	}
	lines := arrayLineValues(initializer.Child("expr"))
	if len(lines) != 0 {
		return lines
	}
	return appendedTemplateLines(writes, initializer, argument)
}

func arrayLineValues(array engine.Match) []engine.Match {
	var lines []engine.Match
	for _, item := range array.ChildrenIn("items") {
		lines = append(lines, item.Child("value"))
	}
	return lines
}

func appendedTemplateLines(reads []Interaction, initializer, argument engine.Match) []engine.Match {
	var lines []engine.Match
	var previous engine.Match
	function := enclosingFunction(argument)
	for _, read := range reads {
		index := read.Node.Parent()
		assignment := index.Parent()
		if index.Kind() != "Expr_ArrayDimFetch" || index.Child("dim").Exists() ||
			!isAssignmentTarget(index) || read.Node != index.Child("var") ||
			!initializer.EndsBefore(assignment) || !assignment.EndsBefore(argument) || enclosingFunction(assignment) != function {
			continue
		}
		statement := assignment.Parent()
		if previous.Exists() && previous.Next() != statement {
			if engine.HasTemplateLines(lines) {
				return lines
			}
			lines = nil
		}
		lines = append(lines, templateLine(assignment.Child("expr")))
		previous = statement
	}
	return lines
}

func templateLine(line engine.Match) engine.Match {
	if line.LiteralKind() == "string" {
		if text, _ := line.Text(); strings.TrimSpace(text) != "" {
			return line
		}
		return engine.Match{}
	}
	if line.Kind() == "Expr_BinaryOp_Concat" {
		if left := templateLine(line.Child("left")); left.Exists() {
			return left
		}
		return templateLine(line.Child("right"))
	}
	for _, part := range line.ChildrenIn("parts") {
		if part.Kind() == "InterpolatedStringPart" {
			if text, _ := part.Text(); strings.TrimSpace(text) != "" {
				return part
			}
		}
	}
	return engine.Match{}
}
