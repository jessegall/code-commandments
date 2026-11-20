package engine

func (m Match) RepeatsArrowReturnType() bool {
	read := m.grammar().ArrowReturn
	return read != nil && read(m)
}

func (m Match) ReturnsNullInDefaultArm() bool {
	read := m.grammar().NullDefault
	return read != nil && read(m)
}

func (m Match) IsMemberAfterMethod() bool {
	return m.isMemberBelow(func(previous, _ int) bool { return previous >= 3 })
}

func (m Match) IsMemberOutOfOrder() bool {
	return m.isMemberBelow(func(previous, member int) bool { return previous < 3 && previous > member })
}

func (m Match) isMemberBelow(check func(int, int) bool) bool {
	read := m.grammar().MemberOrder
	if read == nil {
		return false
	}
	order := read(m)
	if order == 0 || order >= 3 {
		return false
	}
	for _, previous := range m.EnclosingType().Members() {
		if previous.node == m.node {
			break
		}
		if check(read(previous), order) {
			return true
		}
	}
	return false
}

func (m Match) ReturnsPositionalTuple() bool {
	read := m.grammar().TupleSlots
	if !m.Is(Function) || read == nil || m.IsOverride() {
		return false
	}
	seen := map[string]bool{}
	for _, slot := range read(m.ReturnType()) {
		if shape := slot.Typed(); shape != nil && shape.Text != "" {
			if seen[shape.Text] {
				return true
			}
			seen[shape.Text] = true
			continue
		}
		if text := slot.Written(); text != "" {
			if seen[text] {
				return true
			}
			seen[text] = true
		}
	}
	return false
}

func (m Match) AssemblesTemplate() bool {
	read := m.grammar().JoinedLines
	if read == nil || !m.Is(Call) {
		return false
	}
	lines := read(m)
	if len(lines) < 3 {
		return false
	}
	fixed := 0
	for _, line := range lines {
		if line.LiteralKind() == "string" {
			fixed++
		}
	}
	return fixed >= 2
}
