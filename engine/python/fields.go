package python

import (
	"slices"
	"strings"
)

var scalars = []string{"str", "int", "float", "bool"}

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

func (p *Program) coupledValues(class Node, values map[string]bool, fieldCount int) bool {
	tested := class.AttributesTestedForAbsence()
	occurrences := map[string]int{}
	for _, group := range p.assembledGroups(class) {
		var kept, guarded []string
		for _, name := range group {
			if !values[name] {
				continue
			}
			kept = append(kept, name)
			if !slices.Contains(tested, name) {
				continue
			}
			guarded = append(guarded, name)
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

func (p *Program) isValue(annotation Node) bool {
	if optional, ok := annotation.OptionalOf(); ok {
		annotation = optional
	}
	spelled := annotation.SpelledType()
	_, dataclass := p.Dataclass(annotation)

	return slices.Contains(scalars, spelled) || p.IsEnum(annotation) || dataclass
}

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

func (n Node) SpelledType() string {
	if text, ok := n.Text(); ok {
		return text
	}

	return n.DottedName()
}

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

func (n Node) isFakeAnswer() bool {
	return n.Kind() == "Constant" && !n.IsNone()
}
