package engine

import (
	"slices"
	"strings"
)

func (m Match) HoldsCoupledFields() bool {
	if !m.Is(Function) || m.IsConstructor() || m.methodOwner() == "" {
		return false
	}
	writes := map[string]bool{}
	for _, node := range m.OwnDescendants() {
		left, _ := node.assignmentSides()
		if field := fieldOnSelf(left); field.Exists() {
			writes[field.MemberName()] = true
		}
	}
	if len(writes) < 2 {
		return false
	}
	for _, node := range m.OwnDescendants() {
		if !node.Is(Conjunction) {
			continue
		}
		tested := map[string]bool{}
		for _, read := range node.Descendants() {
			if field := fieldOnSelf(read); field.Exists() && writes[field.MemberName()] {
				tested[field.MemberName()] = true
			}
		}
		if len(tested) >= 2 {
			return true
		}
	}
	return false
}

func (m Match) EnviesAnotherObject() bool {
	if !m.Is(Function) || m.IsConstructor() || m.IsTest() {
		return false
	}
	groups := map[string]map[string]bool{}
	structural := map[string]bool{}
	subjects := map[string]Match{}
	for _, read := range m.OwnDescendants() {
		if fieldOnSelf(read).Exists() {
			return false
		}
		parent := read.Parent()
		if !read.Is(MemberAccess) || !read.Receiver().Is(Identifier) ||
			parent.Is(Call) && parent.calledNode().node == read.node {
			continue
		}
		subject := read.Receiver()
		if reactive := m.grammar().ReactiveMember; reactive != nil && reactive(read) {
			continue
		}
		name := subject.Name()
		subjects[name] = subject
		fields := groups[name]
		if fields == nil {
			fields = map[string]bool{}
			groups[name] = fields
		}
		fields[read.MemberName()] = true
		if parent.Is(Loop) || parent.Is(MemberAccess) && parent.Receiver().node == read.node {
			structural[name] = true
		}
	}
	for name, fields := range groups {
		subject := subjects[name]
		if len(fields) >= 3 && structural[name] && subject.hasLocalTypeDeclaration() &&
			!m.ownsRecordBehavior(subject) && !m.translatesBetweenModules(subject) {
			return true
		}
	}
	return false
}

func (m Match) ownsRecordBehavior(subject Match) bool {
	owns := m.grammar().OwnsRecordBehavior
	if owns == nil {
		return false
	}
	declaration := subject.typeDeclaration()

	return declaration.Source() == m.Source() && owns(declaration)
}

func (m Match) translatesBetweenModules(subject Match) bool {
	owner := m.moduleName()
	from := subject.typeDeclaration().moduleName()
	result := m.declaresType(m.ResultType())
	to := result.moduleName()
	if owner == "" || from == "" || to == "" || from == owner || to == owner || from == to {
		return false
	}
	return slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool {
		return node.Is(Construction) && node.typeDeclaration().Identity() == result.Identity()
	})
}

func (m Match) HandsOutCollection() bool {
	read := m.returnedValue()
	if !read.Is(MemberAccess) || !read.Receiver().isSelfReceiver() {
		return false
	}
	typed := read.Typed()
	return typed != nil && !typed.ValueType && !strings.HasPrefix(typed.Text, "readonly ") &&
		(typed.Kind == "array" || typed.Kind == "map" ||
			slices.Contains([]string{"Set", "Map", "Array"}, typed.Name))
}

func (m Match) DeclaresValueEquality() bool {
	if !m.Is(TypeDeclaration) {
		return false
	}
	for _, method := range m.Methods() {
		if strings.EqualFold(method.Name(), "equals") && len(method.Parameters()) == 1 && method.returnsBoolean() {
			return true
		}
	}
	return false
}

func (m Match) MutatesValueObject() bool {
	if !m.Is(Function) || m.IsConstructor() {
		return false
	}
	owner := m.EnclosingType()
	if !owner.Exists() {
		for _, declaration := range m.Codebase().Declarations(m.methodOwner()) {
			owner = declaration
			break
		}
	}
	kind := owner.TypeKind()
	value := kind == "record" || kind == "struct"
	if read := m.grammar().IsValueClass; read != nil && owner.Exists() {
		value = read(owner)
	}
	if !value {
		return false
	}
	return slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool {
		left, _ := node.assignmentSides()
		return left.Is(MemberAccess) && left.Receiver().isSelfReceiver()
	})
}

func (m Match) MutatesStaticState() bool {
	read := m.grammar().StaticStorage
	left, _ := m.assignmentSides()
	if read == nil || !left.Exists() || !m.EnclosingFunction().Exists() {
		return false
	}
	for _, declaration := range m.Codebase().Declarations(left.Refers()) {
		if read(declaration) {
			return true
		}
	}
	return read(left.Declared())
}

func (m Match) IsConstructorSideEffect() bool {
	if !m.Is(Call) || !m.Parent().Is(ExpressionStatement) {
		return false
	}
	constructor := m.EnclosingFunction()
	receiver := m.Receiver()
	return constructor.IsConstructor() && (fieldOnSelf(receiver).Exists() ||
		receiver.Is(Identifier) && slices.ContainsFunc(constructor.Parameters(), func(parameter Match) bool {
			return receiver.Name() == parameter.Name()
		}))
}
