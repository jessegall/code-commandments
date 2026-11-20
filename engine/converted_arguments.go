package engine

import (
	"slices"
	"strconv"
	"strings"
)

// CallConversion records which parameter slots a call supplies and its first conversion.
type CallConversion struct {
	Slots []string
	Key   string
}

func (m Match) ConversionEvidence() CallConversion {
	if m.IsTest() {
		return CallConversion{}
	}
	if read := m.grammar().CallConversion; read != nil {
		return read(m)
	}
	target := m.TargetSymbol()
	if target == "" || len(m.Codebase().Declarations(target)) == 0 {
		return CallConversion{}
	}
	var evidence CallConversion
	for position, argument := range m.Arguments() {
		slot := target + "#" + strconv.Itoa(position)
		evidence.Slots = append(evidence.Slots, slot)
		if evidence.Key == "" && m.parameterAt(position).isScalarType() {
			if converter := argument.conversionName(); converter != "" {
				evidence.Key = slot + "=" + converter
			}
		}
	}
	return evidence
}

func (m Match) isScalarType() bool {
	typed := m.Typed()
	if typed == nil {
		return false
	}
	return slices.Contains(m.grammar().ScalarTypes, typed.Text)
}

func (m Match) conversionName() string {
	if !m.Is(Call) || !m.isScalarType() {
		return ""
	}
	arguments := m.Arguments()
	if len(arguments) != 1 || arguments[0].isScalarType() || arguments[0].Typed() == nil {
		return ""
	}
	if target := m.TargetSymbol(); target != "" {
		return target
	}
	if callee := m.calledNode(); callee.Is(Identifier) && callee.Name() == m.Typed().Text {
		return callee.Name()
	}
	return ""
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
