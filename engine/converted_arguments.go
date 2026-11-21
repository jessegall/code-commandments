package engine

import (
	"slices"
	"strings"
)

// CallConversion records which parameter slots a call supplies and its first conversion.
type CallConversion struct {
	Slots []string
	Key   string
}

func (m Match) isScalarType() bool {
	typed := m.Typed()
	if typed == nil {
		return false
	}
	return slices.Contains(m.grammar().ScalarTypes, typed.Text)
}

func ConvertedCalls(calls []Match, read func(Match) CallConversion) []Match {
	counts := map[string]int{}
	keys := make([]string, len(calls))
	for at, call := range calls {
		if call.file != nil && call.file.IsFrozen() {
			continue
		}
		evidence := read(call)
		for _, slot := range evidence.Slots {
			counts[slot]++
		}
		keys[at] = evidence.Key
	}
	var findings []Match
	for _, at := range DominantConversionPositions(len(calls), func(at int) (string, bool) { return keys[at], keys[at] != "" }, counts) {
		findings = append(findings, calls[at])
	}
	return findings
}

func DominantConversionPositions(count int, key func(int) (string, bool), supplied map[string]int) []int {
	var findings []int
	for _, group := range Recurring(count, key, 2) {
		read, _ := key(group[0])
		slot, _, split := strings.Cut(read, "=")
		if sites := supplied[slot]; split && sites > 0 && len(group)*2 >= sites {
			findings = append(findings, group...)
		}
	}
	return findings
}
