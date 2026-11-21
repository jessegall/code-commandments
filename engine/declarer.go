package engine

import "slices"

func (m Match) Declaring() Match {
	if m.file == nil {
		return Match{}
	}

	read, holder := m.names()
	if read == "" {
		return Match{}
	}

	held := holder.TypeName()
	if held == "" {
		return Match{}
	}

	for _, class := range m.line(held) {
		if declaration, declares := m.declaration(class, read); declares {
			return declaration
		}
	}

	return Match{}
}

func (m Match) names() (string, Match) {
	switch {
	case m.Is(MemberAccess):
		return m.NameRead(), m.Receiver()
	case m.Is(Comparison):
		children := m.Children()
		if len(children) == 0 || !children[0].Is(MemberAccess) {
			return "", Match{}
		}

		return children[0].NameRead(), children[0].Receiver()
	case m.HasFlag("named"):
		return m.Name(), subjectOf(m.Parent())
	case m.Is(Call):
		callee := m.calledNode()
		if !callee.Is(MemberAccess) {
			return "", Match{}
		}

		return callee.NameRead(), callee.Receiver()
	}

	return "", Match{}
}

func (m Match) calledNode() Match {
	for _, field := range calleeFields {
		if called := m.Child(field); called.Exists() {
			return called
		}
	}

	return Match{}
}

func subjectOf(call Match) Match {
	if !call.Is(Call) && !call.Is(Construction) {
		return Match{}
	}

	if receiver := call.Receiver(); receiver.Exists() {
		return receiver
	}

	for _, argument := range call.Arguments() {
		if !argument.HasFlag("named") {
			return argument
		}
	}

	return Match{}
}

func (m Match) Declared() Match {
	if m.file == nil {
		return Match{}
	}

	if resolved := m.resolvedTarget(); resolved.Exists() {
		return resolved
	}

	read, _ := m.names()
	declaring := m.Declaring()
	if read == "" || !declaring.Exists() {
		return Match{}
	}

	for _, member := range declaring.Members() {
		if member.DeclaredName() == read {
			return member
		}
	}

	return Match{}
}

func (m Match) resolvedTarget() Match {
	if m.node == nil || m.node.Target == nil || m.node.Target.Symbol == "" {
		return Match{}
	}

	for _, declaration := range m.file.Codebase().Declarations(m.node.Target.Symbol) {
		return declaration
	}

	return Match{}
}

func (m Match) DeclaredElsewhere() bool {
	declaring := m.Declaring()

	return declaring.Exists() && declaring.File() != m.File()
}

func (m Match) Declarer() string {
	declaring := m.Declaring()
	if !declaring.Exists() {
		return ""
	}

	return declaring.Identity()
}

func (m Match) line(class string) []string {
	for _, declaration := range m.file.Codebase().Declarations(class) {
		if declaration.Is(TypeDeclaration) {
			return append([]string{class}, declaration.Lineage()...)
		}
	}

	return []string{class}
}

func (m Match) declaration(class, member string) (Match, bool) {
	for _, declaration := range m.file.Codebase().Declarations(class) {
		if !declaration.Is(TypeDeclaration) {
			continue
		}
		if slices.ContainsFunc(declaration.Members(), func(declared Match) bool { return declared.DeclaredName() == member }) {
			return declaration, true
		}
	}

	return Match{}, false
}

func (m Match) Readers() []Match {
	if m.file == nil || !m.Exists() {
		return nil
	}

	var readers []Match
	for _, reader := range readersOf(m.file.Codebase())[m.Location()] {
		if reader.node == m.node || m.isAbove(reader) || reader.IsOverwritten() {
			continue
		}

		readers = append(readers, reader)
	}

	return readers
}

func (m Match) IsOverwritten() bool {
	parent := m.Parent()
	if !parent.Is(Assignment) || !slices.Contains([]string{"=", ""}, parent.operator()) {
		return false
	}

	written := parent.Children()

	return len(written) > 0 && m.isAtOrWithin(written[0])
}

func (m Match) isAtOrWithin(other Match) bool {
	return m.node == other.node || other.isAbove(m)
}

func (m Match) operator() string {
	if m.node == nil || m.node.Facts == nil {
		return ""
	}

	return m.node.Operator
}

func (m Match) DeclaresAField() bool {
	if !m.Is(Parameter) {
		return false
	}

	for _, member := range m.EnclosingType().Members() {
		if member.node == m.node {
			return true
		}
	}

	return false
}

func (m Match) IsUnreadMember() bool {
	if m.Is(Function) || m.Is(TypeDeclaration) {
		return false
	}

	if m.Role() != "member" && !m.DeclaresAField() {
		return false
	}

	return !m.IsImplicit() && !m.IsOverride() && len(m.Readers()) == 0 && len(m.ReadingReferrers()) == 0
}

func (m Match) ReadingReferrers() []Match {
	var reading []Match
	for _, referrer := range m.Referrers() {
		if referrer.IsOverwritten() {
			continue
		}

		reading = append(reading, referrer)
	}

	return reading
}

func readersOf(c *Codebase) map[string][]Match {
	return Analysis(c, "readers", func(c *Codebase) map[string][]Match {
		index := map[string][]Match{}
		for _, file := range c.files {
			for _, node := range file.Nodes() {
				reader := Match{node: node, file: file}
				if declared := reader.Declared(); declared.Exists() {
					index[declared.Location()] = append(index[declared.Location()], reader)
				}
			}
		}

		return index
	})
}
