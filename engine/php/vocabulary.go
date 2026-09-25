package php

import (
	"slices"
	"strconv"

	"github.com/jessegall/code-commandments/engine"
)

// Vocabulary is the named string constants a codebase spells its call slots with: which classes' constants fill
// each argument slot of a method, and the constant each such class names a string value by.
type Vocabulary struct {
	classesBySlot map[string][]string
	namesByClass  map[string]map[string]string
}

var vocabularies = Memoised(func(codebase *engine.Codebase) Vocabulary {
	vocabulary := Vocabulary{classesBySlot: map[string][]string{}, namesByClass: map[string]map[string]string{}}
	for _, declaration := range codebase.Where(func(m engine.Match) bool { return isClassLike(m) }).Get() {
		owner := declaration.Node().Symbol
		if owner == "" {
			continue
		}
		for _, member := range (Node{Match: declaration}).In("stmts") {
			modifiers := member.Node().Modifiers
			if member.Kind() != "Stmt_ClassConst" || slices.Contains(modifiers, "private") || slices.Contains(modifiers, "protected") {
				continue
			}
			for _, constant := range (Node{Match: member}).In("consts") {
				value, ok := constant.Child("value").Text()
				if constant.Child("value").Kind() != "Scalar_String" || !ok || value == "" || isNumeric(value) {
					continue
				}
				if vocabulary.namesByClass[owner] == nil {
					vocabulary.namesByClass[owner] = map[string]string{}
				}
				vocabulary.namesByClass[owner][value] = constant.Name()
			}
		}
	}
	for _, fetch := range codebase.WhereKind("Expr_ClassConstFetch").Get() {
		class := fetch.Child("class")
		slot := slotOf(fetch)
		if !isName(class) || slot == "" || vocabulary.namesByClass[class.Name()] == nil {
			continue
		}
		if !slices.Contains(vocabulary.classesBySlot[slot], class.Name()) {
			vocabulary.classesBySlot[slot] = append(vocabulary.classesBySlot[slot], class.Name())
		}
	}

	return vocabulary
})

// VocabularyOf is the codebase's constant vocabulary.
func VocabularyOf(codebase *engine.Codebase) Vocabulary {
	return vocabularies.Of(codebase)
}

// NameFor is the Class::CONSTANT that names the string literal's value, where the slot it fills is spelled with that
// class's constants elsewhere; empty when none does.
func (v Vocabulary) NameFor(literal engine.Match) string {
	value, ok := literal.Text()
	if literal.Kind() != "Scalar_String" || !ok {
		return ""
	}
	slot := slotOf(literal)
	if slot == "" {
		return ""
	}
	for _, class := range v.classesBySlot[slot] {
		if name, named := v.namesByClass[class][value]; named {
			return ShortName(class) + "::" + name
		}
	}

	return ""
}

// slotOf is the method and argument position an expression is passed in, as Class::method#position; empty unless
// it is an argument of a static call or of a call on $this.
func slotOf(expression engine.Match) string {
	argument := expression.Parent()
	call := argument.Parent()
	if argument.Kind() != "Arg" || call.Kind() != "Expr_MethodCall" && call.Kind() != "Expr_StaticCall" {
		return ""
	}
	target := ""
	switch name := call.Child("name"); {
	case name.Kind() != "Identifier":
		return ""
	case call.Kind() == "Expr_MethodCall":
		owner := EnclosingClassName(call)
		if !(Node{Match: call}).IsThisCall() || owner == "" {
			return ""
		}
		target = owner + "::" + name.Name()
	default:
		class := call.Child("class")
		if !isName(class) {
			return ""
		}
		owner := class.Name()
		if owner == "self" || owner == "static" {
			if enclosing := EnclosingClassName(call); enclosing != "" {
				owner = enclosing
			}
		}
		target = owner + "::" + name.Name()
	}
	position := 0
	for _, candidate := range call.Children() {
		if candidate.Node().Field != "args" {
			continue
		}
		if candidate.Node() == argument.Node() {
			return target + "#" + strconv.Itoa(position)
		}
		position++
	}

	return ""
}
