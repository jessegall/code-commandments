package php

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsLibraryRowFilter() bool {
	filter := n.CallbackConsumer()
	if !CallsGlobalFunction(filter, "array_filter") {
		return false
	}
	arguments := Arguments(filter)
	if len(arguments) != 2 {
		return false
	}
	callback := n.EnclosingFunctionLike()
	reads := 0
	for _, read := range callback.OwnDescendants() {
		if read.Kind() == "Expr_ArrayDimFetch" {
			reads++
		}
	}
	source := arguments[0].Child("value")
	return reads == 1 && isLibraryArraySource(source)
}

func isLibraryArraySource(call engine.Match) bool {
	codebase := call.Codebase()
	if codebase.IsLibraryType(contract.PHP, CallReceiverClass(call)) {
		return true
	}
	receiver := call.Child("var")
	if memberOnThis(receiver) == "" {
		return false
	}
	constructor, declared := Method(EnclosingClass(call), "__construct")
	if !declared {
		return false
	}
	found := false
	for _, assignment := range constructor.OwnDescendants() {
		if assignment.Kind() != "Expr_Assign" || !assignment.Child("var").SameSyntax(receiver) {
			continue
		}
		if !isLibraryProperty(assignment.Child("expr")) {
			return false
		}
		found = true
	}
	return found
}

func isLibraryProperty(property engine.Match) bool {
	if property.Kind() != "Expr_PropertyFetch" {
		return false
	}
	codebase := property.Codebase()
	class := TypesOf(codebase).TypeAtRead(property.Child("var"))
	if codebase.IsLibraryType(contract.PHP, class) {
		return true
	}
	declaration, found := ProgramOf(codebase).Declaration(class)
	if !found {
		return false
	}
	doc, ok := DocComment(declaration)
	if !ok {
		return false
	}
	for _, line := range docblockContent(doc.Text) {
		words := strings.Fields(line)
		if len(words) == 2 && words[0] == "@mixin" && codebase.IsLibraryType(contract.PHP, Resolve(words[1], declaration.Source())) {
			return true
		}
	}
	return false
}
