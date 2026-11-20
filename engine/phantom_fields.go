package engine

import "github.com/jessegall/code-commandments/contract"

func (m Match) PhantomFields() []Match {
	grammar := m.grammar()
	if grammar.PhantomFields != nil {
		return grammar.PhantomFields(m)
	}
	if grammar.NullableFields == nil {
		return nil
	}
	var found []Match
	for _, field := range grammar.NullableFields(m) {
		verdict := m.fieldVerdict(field)
		if verdict.Assume > 0 && verdict.Guard == 0 {
			found = append(found, field)
		}
	}
	return found
}

func (owner Match) fieldVerdict(field Match) FlowVerdict {
	var verdict FlowVerdict
	name := field.NameRead()
	if name == "" {
		name = field.DeclaredName()
	}
	for _, read := range memberReads(owner.Codebase().Of(owner.Source().Language()))[name] {
		if !owner.isFieldRead(read, name) {
			continue
		}
		parent := read.Grouped().Parent()
		switch {
		case read.acknowledgesAbsence():
			verdict.Guard++
		case parent.Is(Assignment) && len(parent.Children()) == 2 && parent.Children()[0].node == read.node:
			if parent.Children()[1].IsNullish() {
				verdict.Guard++
			}
		case parent.Is(MemberAccess) && parent.Receiver().node == read.node:
			verdict.Assume++
		}
	}
	return verdict
}

func memberReads(codebase *Codebase) map[string][]Match {
	return Analysis(codebase, "field-reads-by-name", func(codebase *Codebase) map[string][]Match {
		found := map[string][]Match{}
		for _, read := range codebase.Reporting().WhereIs(MemberAccess).Get() {
			if name := read.NameRead(); name != "" {
				found[name] = append(found[name], read)
			}
		}
		return found
	})
}

func (owner Match) isFieldRead(read Match, name string) bool {
	if name == "" || read.NameRead() != name {
		return false
	}
	receiver := read.Receiver()
	if receiver.Is(SelfReference) && read.EnclosingType().node == owner.node {
		return true
	}
	typeName := nullableTypeName(receiver.Typed())
	if typeName == owner.Identity() {
		return true
	}
	types, available := owner.Types()
	return available && IsA(types, typeName, owner.Identity())
}

func nullableTypeName(typed *contract.Type) string {
	for typed != nil {
		if typed.Name != "" {
			return typed.Name
		}
		typed = typed.Element
	}
	return ""
}

func (m Match) AbsenceTrace() []string {
	if read := m.grammar().AbsenceTrace; read != nil {
		return read(m)
	}
	return nil
}
