package python

import (
	"slices"
	"strings"
)

// scalars is the builtin types a value field holds.
var scalars = []string{"str", "int", "float", "bool"}

// IsCoupled says whether the class's own value fields are really one object. A clump is made of values (scalars,
// enums, dataclasses), never collaborators, and shows in one of two shapes: fields assembled into one value
// together, again and again or guarded for absence together first, and a field that mirrors what a sibling field
// already holds.
func (p *Program) IsCoupled(class Node) bool {
	fields := class.FieldNames()
	if len(fields) < 2 {
		return false
	}
	values := map[string]bool{}
	for _, field := range fields {
		if annotation, ok := class.AttributeAnnotation(field); ok && p.isValue(annotation) {
			values[field] = true
		}
	}

	return p.coupledValues(class, values, len(fields)) || p.mirrorsASibling(class, fields)
}

// coupledValues says whether two or more value fields are assembled into one value by themselves, a proper subset
// of the fields, the same group in two or more places or two or more of it guarded for absence together. Two
// among a wide projection of every field is a mapping, not a clump.
func (p *Program) coupledValues(class Node, values map[string]bool, fieldCount int) bool {
	tested := class.AttributesTestedForAbsence()
	occurrences := map[string]int{}
	for _, group := range p.assembledGroups(class) {
		var kept, guarded []string
		for _, name := range group {
			if values[name] {
				kept = append(kept, name)
				if slices.Contains(tested, name) {
					guarded = append(guarded, name)
				}
			}
		}
		if len(kept) < 2 || len(kept) >= fieldCount {
			continue
		}
		if len(guarded) >= 2 && len(guarded)*2 >= len(kept) {
			return true
		}
		slices.Sort(kept)
		key := strings.Join(kept, ",")
		if occurrences[key]++; occurrences[key] >= 2 {
			return true
		}
	}

	return false
}

// assembledGroups is every group of two or more distinct own fields handed, as they are, to one tuple, list or
// class being built. A plain call passing them along is forwarding, not assembling one thing.
func (p *Program) assembledGroups(class Node) [][]string {
	var groups [][]string
	for _, expression := range class.ExpressionsWithin() {
		var parts []Node
		switch {
		case expression.Kind() == "Tuple" || expression.Kind() == "List":
			parts = expression.ChildrenIn("elts")
		case expression.IsCall() && p.DeclaresClass(expression.Callee().DottedName()):
			parts = expression.Arguments()
			for _, keyword := range expression.Keywords() {
				parts = append(parts, keyword.Child("value"))
			}
			slices.SortFunc(parts, func(a, b Node) int { return a.Node().Span.Start - b.Node().Span.Start })
		}
		var fields []string
		for _, part := range parts {
			if field := part.SelfAttribute(); field != "" && !slices.Contains(fields, field) {
				fields = append(fields, field)
			}
		}
		if len(fields) >= 2 {
			groups = append(groups, fields)
		}
	}

	return groups
}

// mirrorsASibling says whether a field mirrors a value a sibling field already holds: `workflow_id` beside a
// `workflow` whose class has an `id` of the same type. The datum then lives in two places.
func (p *Program) mirrorsASibling(class Node, fields []string) bool {
	for _, object := range fields {
		annotation, ok := class.AttributeAnnotation(object)
		if !ok {
			continue
		}
		sibling, ok := p.ClassCalled(annotation.SpelledType())
		if !ok {
			continue
		}
		for _, inner := range sibling.FieldNames() {
			mirror := object + "_" + inner
			ours, mirrored := class.AttributeAnnotation(mirror)
			theirs, held := sibling.AttributeAnnotation(inner)
			if slices.Contains(fields, mirror) && mirrored && held && theirs.SpelledType() != "" && theirs.SpelledType() == ours.SpelledType() {
				return true
			}
		}
	}

	return false
}

// isValue says whether the annotation is a value, a builtin scalar, an enum or a dataclass, rather than a
// collaborator.
func (p *Program) isValue(annotation Node) bool {
	if optional, ok := annotation.OptionalOf(); ok {
		annotation = optional
	}
	spelled := annotation.SpelledType()
	_, dataclass := p.Dataclass(spelled)

	return slices.Contains(scalars, spelled) || p.IsEnum(spelled) || dataclass
}

// AttributesTestedForAbsence is the class's own attributes its body compares to None with `is` or `is not`.
func (n Node) AttributesTestedForAbsence() []string {
	var names []string
	for _, statement := range n.ChildrenIn("body") {
		for _, part := range statement.evaluatedIn() {
			if operand, ok := part.NoneTestedOperand(); ok && operand.SelfAttribute() != "" && !slices.Contains(names, operand.SelfAttribute()) {
				names = append(names, operand.SelfAttribute())
			}
		}
	}

	return names
}

// SetsOutsideInit says whether a method of the class other than __init__ writes the attribute on self.
func (n Node) SetsOutsideInit(name string) bool {
	for _, method := range n.Methods() {
		if method.Name() == "__init__" {
			continue
		}
		for _, statement := range method.statementsIn() {
			for _, target := range statement.writtenTargets() {
				if target.SelfAttribute() == name {
					return true
				}
			}
		}
	}

	return false
}

// SpelledType is the type an annotation spells: a string annotation's text, else its dotted name.
func (n Node) SpelledType() string {
	if text, ok := n.Text(); ok {
		return text
	}

	return n.DottedName()
}

// OptionalOf is the type an optional annotation makes optional: `T` in `Optional[T]`, `T | None` and `None | T`.
func (n Node) OptionalOf() (Node, bool) {
	if n.Kind() == "Subscript" && slices.Contains([]string{"Optional", "typing.Optional"}, n.Child("value").DottedName()) {
		return n.Child("slice"), true
	}
	if n.Kind() != "BinOp" || n.Node().Operator != "|" {
		return Node{}, false
	}
	left, right := n.Child("left"), n.Child("right")
	if right.IsNone() {
		return left, true
	}

	return right, left.IsNone()
}

// MasksOwnState says whether the expression answers an absent scratch field of its own class with a literal,
// `self.period.includes(day) if self.period else False` or `getattr(self.period, "label", "none")`, where the
// field is optional only because an operation sets it part-way. At the read it should be there; the literal
// answers a state that can only be a bug.
func (n Node) MasksOwnState() bool {
	field := n.maskedField()
	class := n.EnclosingFunction().Parent()
	if field == "" || class.Kind() != "ClassDef" || !class.SetsOutsideInit(field) {
		return false
	}
	annotation, ok := class.AttributeAnnotation(field)
	if !ok {
		return false
	}
	_, optional := annotation.OptionalOf()

	return optional
}

// maskedField is the own field the expression reaches into with a literal standing in when it is missing; empty
// when it is no such mask.
func (n Node) maskedField() string {
	if n.IsCall() && n.Callee().DottedName() == "getattr" {
		arguments := n.Arguments()
		if len(arguments) == 3 && len(n.Keywords()) == 0 && arguments[2].isFakeAnswer() {
			return arguments[0].SelfAttribute()
		}

		return ""
	}
	if n.Kind() != "IfExp" || !n.Child("orelse").isFakeAnswer() {
		return ""
	}
	test := n.Child("test")
	if operand, ok := test.NoneTestedOperand(); ok {
		test = operand
	}
	field := test.SelfAttribute()
	reached := slices.ContainsFunc(n.Child("body").evaluatedIn(), func(part Node) bool {
		return part.Kind() == "Attribute" && part.Child("value").SelfAttribute() == field
	})
	if field == "" || !reached {
		return ""
	}

	return field
}

// isFakeAnswer says whether the value is a literal other than None: the made-up answer, not an honest absence.
func (n Node) isFakeAnswer() bool {
	return n.Kind() == "Constant" && !n.IsNone()
}
