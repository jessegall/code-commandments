package engine

import "slices"

func (m Match) IsConstClassEnum() bool {
	read := m.grammar().ConstClass
	return read != nil && read(m)
}

func (m Match) KeepsEnumSideTable() bool {
	read := m.grammar().EnumTable
	return read != nil && read(m)
}

func SideTables(codebase *Codebase) []Match {
	for _, file := range codebase.Files() {
		grammar := grammarOf(file.Language())
		if grammar.SideTables != nil {
			found := grammar.SideTables(codebase)
			if grammar.EnumTable != nil {
				found = append(found, codebase.Where(Match.KeepsEnumSideTable).Get()...)
			}
			return found
		}
	}
	return codebase.Where(Match.KeepsEnumSideTable).Get()
}

// SideTableReading supplies the syntax of a registry and its adjacent membership table.
type SideTableReading struct {
	Declared   []Match
	Checked    []Match
	Registered func(Match) []string
	Listed     func(Match) []string
	Name       func(Match) string
	AsksAbout  func(Match, string) bool
}

func (read SideTableReading) Findings() []Match {
	registered, checked := map[string]map[string]bool{}, map[string][]Match{}
	for _, declaration := range read.Declared {
		for _, name := range read.Registered(declaration) {
			if registered[declaration.File()] == nil {
				registered[declaration.File()] = map[string]bool{}
			}
			registered[declaration.File()][name] = true
		}
	}
	for _, check := range read.Checked {
		checked[check.File()] = append(checked[check.File()], check)
	}
	var findings []Match
	for _, declaration := range read.Declared {
		known := registered[declaration.File()]
		if len(known) == 0 {
			continue
		}
		members := read.Listed(declaration)
		name := read.Name(declaration)
		if name == "" || len(members) == 0 || slices.ContainsFunc(members, func(member string) bool { return !known[member] }) {
			continue
		}
		if slices.ContainsFunc(checked[declaration.File()], func(check Match) bool { return read.AsksAbout(check, name) }) {
			findings = append(findings, declaration)
		}
	}
	return findings
}
