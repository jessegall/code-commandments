package csharp

import (
	"slices"
	"sort"
)

// StateFlow is how a type's own state moves through its members: which members read which of it, which fields may
// hold nothing, and every value a field is given.
type StateFlow struct {
	owner Node
	state []string
}

// FlowOf is the state flow of the type declaration.
func FlowOf(owner Node) StateFlow {
	return StateFlow{owner: owner, state: owner.StateNames()}
}

// MemberReads is the state one member reads, by name, sorted.
type MemberReads struct {
	Member string
	Reads  []string
}

// ReadsByMember is the state each member other than a constructor or a field reads, in the order the type declares
// them; a member that reads none is left out.
func (f StateFlow) ReadsByMember() []MemberReads {
	var reads []MemberReads
	for _, member := range f.owner.All() {
		if !member.readsState() || member.Name() == "" {
			continue
		}
		var read []string
		for _, reference := range f.readsIn(member) {
			if name := reference.MemberName(); !slices.Contains(read, name) {
				read = append(read, name)
			}
		}
		sort.Strings(read)
		if len(read) == 0 {
			continue
		}
		if at := slices.IndexFunc(reads, func(held MemberReads) bool { return held.Member == member.Name() }); at >= 0 {
			reads[at].Reads = read
			continue
		}
		reads = append(reads, MemberReads{Member: member.Name(), Reads: read})
	}

	return reads
}

// readsState says whether the member is one whose reads of the type's state count: any member but a constructor
// or a field.
func (n Node) readsState() bool {
	return n.hierarchyRole() == "member" && !n.Is("ConstructorDeclaration", "FieldDeclaration")
}

// NullableFields is the names the type holds nullable state under: the fields and properties it declares with a
// type that admits `null`.
func (f StateFlow) NullableFields() []string {
	var names []string
	for _, member := range f.owner.All() {
		if member.Is("FieldDeclaration", "PropertyDeclaration") && member.DeclaresNullableState() {
			names = append(names, member.HeldStateNames()...)
		}
	}

	return names
}

// AssignedValues is every value the field is given: its initial value, and every plain assignment to it a member
// makes, a local of the same name aside.
func (f StateFlow) AssignedValues(field string) []Node {
	var values []Node
	for _, member := range f.owner.All() {
		values = append(values, member.InitialValuesOf(field)...)
		for _, write := range member.writes() {
			if write.Is("SimpleAssignmentExpression") && write.At(0).MemberName() == field && !write.At(0).IsShadowedIn(member) {
				values = append(values, write.At(1))
			}
		}
	}

	return values
}

// Reads is every read of the field in the type's members other than its constructors and fields.
func (f StateFlow) Reads(field string) []Node {
	var reads []Node
	for _, member := range f.owner.All() {
		if !member.readsState() {
			continue
		}
		for _, reference := range f.readsIn(member) {
			if reference.MemberName() == field {
				reads = append(reads, reference)
			}
		}
	}

	return reads
}

// readsIn is the references to the type's state the member reads: not its writes' targets, nor a name the member
// declares for itself.
func (f StateFlow) readsIn(member Node) []Node {
	own := slices.DeleteFunc(slices.Clone(f.state), func(name string) bool { return slices.Contains(member.OwnNames(), name) })
	var targets []Node
	for _, write := range member.writes() {
		targets = append(targets, write.At(0))
	}
	var reads []Node
	for _, expression := range member.OutermostExpressions() {
		for _, reference := range expression.OwnStateReferences(own) {
			if !slices.ContainsFunc(targets, func(target Node) bool { return target.Node() == reference.Node() }) {
				reads = append(reads, reference)
			}
		}
	}

	return reads
}

// writes is every write the member makes.
func (n Node) writes() []Node {
	var writes []Node
	for _, expression := range n.expressionParts() {
		if expression.IsWrite() {
			writes = append(writes, expression)
		}
	}

	return writes
}
