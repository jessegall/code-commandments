package engine

import (
	"slices"
	"strings"
)

// Guard reads the independently testable parts of a conjunction.
type Guard struct {
	parts       []Match
	fingerprint string
	substantive bool
	narrows     bool
}

func ReadGuard(fingerprint string, substantive, narrows bool) Guard {
	return Guard{fingerprint: fingerprint, substantive: substantive, narrows: narrows}
}

func (m Match) Guard() Guard {
	if read := m.grammar().Guard; read != nil {
		return read(m)
	}
	if !m.isAnd() || m.Parent().isAnd() {
		return Guard{}
	}
	if parent := m.Parent(); !parent.Is(Branch) && !parent.Is(Loop) && !parent.Is(Return) {
		return Guard{}
	}

	return Guard{parts: m.andParts()}
}

func (m Match) isAnd() bool {
	return m.Is(Conjunction) && m.Operator() == "&&"
}

func (m Match) andParts() []Match {
	if !m.isAnd() {
		return []Match{m}
	}
	var parts []Match
	for _, child := range m.Children() {
		parts = append(parts, child.andParts()...)
	}

	return parts
}

func (guard Guard) Substantive() bool {
	if guard.fingerprint != "" {
		return guard.substantive
	}
	if len(guard.parts) < 2 || !slices.ContainsFunc(guard.parts, func(part Match) bool { return !part.IsTypeTest() }) {
		return false
	}
	reaches := 0
	for _, part := range guard.parts {
		for _, node := range append(part.Descendants(), part) {
			if node.Is(MemberAccess) && node.Parent().Is(Call) {
				continue
			}
			if !node.IsConstant() && (node.Is(MemberAccess) || node.Is(Call)) {
				reaches++
			}
		}
	}

	return reaches >= 2
}

func (guard Guard) NarrowsTypes() bool {
	if guard.fingerprint != "" {
		return guard.narrows
	}
	checks := 0
	for _, part := range guard.parts {
		if part.IsTypeTest() {
			checks++
		}
	}

	return checks >= 2
}

func (guard Guard) Key() (string, bool) {
	if guard.fingerprint != "" {
		return guard.fingerprint, true
	}
	var hashes []string
	for _, part := range guard.parts {
		hash := part.NodeHash(false)
		if hash == "" {
			return "", false
		}
		hashes = append(hashes, hash)
	}
	slices.Sort(hashes)

	return strings.Join(hashes, "|"), len(hashes) > 0
}

func (m Match) IsTypeTest() bool {
	read := m.grammar().TypeTest

	return read != nil && read(m)
}
