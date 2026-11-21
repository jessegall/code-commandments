package packages

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/laravel"
)

// By is where a finding is looked up when a detector asks whether a package excuses it.
type By int

const (
	EnclosingClass By = iota
	EnclosingMethod
	CallbackConsumer
	CallbackResultConsumer
	ReturnConsumer
)

// Exemption is a tag a detector honours, and where it looks a finding up; none means the detector asks itself.
type Exemption struct {
	Tag Tag
	By  []By
}

// Exemptable is a detector that honours exemptions.
type Exemptable interface {
	Exemptions() []Exemption
}

func Exempt(codebase *engine.Codebase, detector Exemptable, findings []engine.Match) []engine.Match {
	return slices.DeleteFunc(slices.Clone(findings), func(finding engine.Match) bool {
		return excused(codebase, detector, finding)
	})
}

func excused(codebase *engine.Codebase, detector Exemptable, finding engine.Match) bool {
	for _, exemption := range detector.Exemptions() {
		if slices.ContainsFunc(exemption.By, func(by By) bool {
			return matchesExemption(codebase, finding, exemption.Tag, by)
		}) {
			return true
		}
	}

	return false
}

func matchesExemption(codebase *engine.Codebase, finding engine.Match, tag Tag, by By) bool {
	node := php.Node{Match: finding}
	switch by {
	case ReturnConsumer:
		return returnOnlyUsedInContract(codebase, finding, tag)
	case CallbackConsumer:
		return requiredCallbackArray(codebase, node, tag)
	case CallbackResultConsumer:
		if call := node.CallbackResultConsumer(); call.Exists() {
			return takesArrayInput(codebase, tag, call)
		}
		return takesArrayInput(codebase, tag, laravel.CollectionMapConsumer(node.CallbackConsumer()))
	}

	return excusesEnclosing(codebase, tag, finding, by)
}

func excusesEnclosing(codebase *engine.Codebase, tag Tag, finding engine.Match, by By) bool {
	class := php.ClassDeclarationName(php.EnclosingClass(finding))
	method := ""
	if by == EnclosingMethod {
		method = php.EnclosingFunctionName(finding)
	}

	return Excuses(codebase, tag, class, method)
}

func requiredCallbackArray(codebase *engine.Codebase, node php.Node, tag Tag) bool {
	call := node.CallbackConsumer()
	class, method := laravel.CallReceiverClass(call), php.CallName(call)
	if class == "" || method == "" {
		return false
	}

	return Excuses(codebase, tag, class, method) || tag == ArrayCallbackContract && node.CallbackMustReturnArray(class)
}

func returnOnlyUsedInContract(codebase *engine.Codebase, finding engine.Match, tag Tag) bool {
	function := (php.Node{Match: finding}).ArrayReturnMethod()
	if !function.Exists() {
		return false
	}
	callers := php.IndexOf(codebase).ResolvedCallersOfMethod(function)
	return len(callers) > 0 && !slices.ContainsFunc(callers, func(caller engine.Match) bool {
		if belongsToContractArray(codebase, caller.Parent()) {
			return false
		}
		consumer := php.ArgumentConsumer(caller)
		if consumer.Exists() {
			return !takesArrayInput(codebase, tag, consumer) && !onlyBatchesArrayInput(codebase, tag, consumer, caller)
		}
		callback := (php.Node{Match: caller}).EnclosingFunctionLike()
		if callback.Kind() != "Expr_ArrowFunction" || callback.Child("expr").Node() != caller.Node() {
			return true
		}
		return !requiredCallbackArray(codebase, php.Node{Match: caller}, ArrayCallbackContract)
	})
}

func belongsToContractArray(codebase *engine.Codebase, item engine.Match) bool {
	if item.Kind() != "ArrayItem" {
		return false
	}
	record := item.Parent()
	node := php.Node{Match: record}
	if !item.HasFlag("spread") {
		return record.ReturnsRecordBag() && node.ProjectsTypedObject()
	}
	if php.CallsGlobalFunction(php.ArgumentConsumer(record), "view") {
		return true
	}
	if !node.IsReturnedValue() {
		return false
	}

	return node.OverridesReturnSignature() ||
		excusesEnclosing(codebase, ArrayReturnContract, record, EnclosingMethod)
}

func takesArrayInput(codebase *engine.Codebase, tag Tag, call engine.Match) bool {
	class, method := laravel.CallReceiverClass(call), php.CallName(call)
	return class != "" && method != "" && Excuses(codebase, tag, class, method)
}

func onlyBatchesArrayInput(codebase *engine.Codebase, tag Tag, call, value engine.Match) bool {
	loop := call.Parent()
	if !php.CallsGlobalFunction(call, "array_chunk") || loop.Kind() != "Stmt_Foreach" || call.Node().InRole() != "expr" {
		return false
	}
	arguments := php.Arguments(call)
	if len(arguments) == 0 || arguments[0].Child("value") != value {
		return false
	}
	batch := loop.Child("valueVar")
	if batch.Kind() != "Expr_Variable" || batch.Name() == "" {
		return false
	}
	uses := php.Trace(batch)
	return len(uses) > 1 && !slices.ContainsFunc(uses, func(use php.Interaction) bool {
		return use.Node != batch && !takesArrayInput(codebase, tag, php.ArgumentConsumer(use.Node))
	})
}
