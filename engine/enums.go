package engine

import (
	"maps"
	"regexp"
	"slices"
	"strings"
)

// Enums stores declared enum values by the symbol that owns them.
type Enums map[string][]string

func EnumsOf(codebase *Codebase) Enums {
	return Analysis(codebase, "enum-values", func(codebase *Codebase) Enums {
		values := Enums{}
		seen := map[string]bool{}
		for _, file := range codebase.Files() {
			language := file.Language()
			if read := grammarOf(language).EnumCatalog; read != nil && !seen[string(language)] {
				maps.Copy(values, read(file.Codebase()))
				seen[string(language)] = true
			}
		}
		for _, declared := range codebase.Reporting().WhereTypeDeclaration().Get() {
			if read := declared.grammar().EnumValues; read != nil {
				if cases := read(declared); len(cases) >= 2 && declared.Identity() != "" {
					values[declared.Identity()] = cases
				}
			}
		}
		return values
	})
}

func (enums Enums) IsIndexed(symbol string) bool {
	_, exists := enums[strings.TrimLeft(symbol, `\`)]
	return symbol != "" && exists
}

var numericText = regexp.MustCompile(`^[ \t\n\r\v\f]*[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?[ \t\n\r\v\f]*$`)

func IsNumericText(text string) bool {
	return numericText.MatchString(text)
}

func (enums Enums) MirroredBy(literals []string) bool {
	var distinct []string
	for _, literal := range literals {
		if !IsNumericText(literal) && !slices.Contains(distinct, literal) {
			distinct = append(distinct, literal)
		}
	}
	if len(distinct) < 2 {
		return false
	}
	for _, cases := range enums {
		if !slices.ContainsFunc(distinct, func(literal string) bool { return !slices.Contains(cases, literal) }) {
			return true
		}
	}
	return false
}

func (m Match) EnumChainOwner() string {
	grammar := m.grammar()
	if grammar.EnumChainOwner != nil {
		return grammar.EnumChainOwner(m)
	}
	if grammar.EnumCase == nil || !m.isOr() || m.Parent().isOr() {
		return ""
	}
	var subject Match
	owner := ""
	var cases []string
	for _, check := range m.orParts() {
		parts := check.Children()
		if !check.Is(Comparison) || len(parts) != 2 || (check.Operator() != "===" && check.Operator() != "==") {
			return ""
		}
		holder, name := grammar.EnumCase(parts[1])
		other := parts[0]
		if holder == "" {
			holder, name = grammar.EnumCase(parts[0])
			other = parts[1]
		}
		if holder == "" || (owner != "" && (holder != owner || !other.SameSyntax(subject))) {
			return ""
		}
		owner, subject = holder, other
		if !slices.Contains(cases, name) {
			cases = append(cases, name)
		}
	}
	if len(cases) >= 2 {
		return owner
	}
	return ""
}

func (m Match) isOr() bool {
	return m.Is(Conjunction) && m.Operator() == "||"
}

func (m Match) orParts() []Match {
	if !m.isOr() {
		return []Match{m}
	}
	var result []Match
	for _, child := range m.Children() {
		result = append(result, child.orParts()...)
	}
	return result
}

func (m Match) EnumMembership() []string {
	if read := m.grammar().EnumMembership; read != nil {
		return read(m)
	}
	return nil
}

func (m Match) EnumArms() []string {
	if read := m.grammar().EnumArms; read != nil {
		return read(m)
	}
	return nil
}
