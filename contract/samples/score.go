package samples

import (
	"cmp"
	"fmt"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// Score is how one language's bridge came out against every marker: occurrences and keys counted exactly, each
// failure, each owed key no marker proves, and each fault that fails the gate on its own.
type Score struct {
	Language   contract.Language
	Passed     int
	Total      int
	Proven     int
	Owed       int
	Wrong      []Occurrence
	Missing    []string
	Faults     []string
	Incomplete bool
}

func ScoreOf(language contract.Language, occurrences []Occurrence, debt Debt, faults []error, incomplete bool) Score {
	score := Score{Language: language, Owed: len(debt.Owed), Incomplete: incomplete}
	for _, fault := range faults {
		score.Faults = append(score.Faults, fault.Error())
	}
	byKey := map[string][]Occurrence{}
	for _, occurrence := range occurrences {
		score.Total++
		if occurrence.Status == Passed {
			score.Passed++
		} else {
			score.Wrong = append(score.Wrong, occurrence)
		}
		byKey[occurrence.Marker.Key] = append(byKey[occurrence.Marker.Key], occurrence)
	}
	for _, exemption := range debt.Exemptions {
		for _, occurrence := range byKey[exemption.Item] {
			if occurrence.Status == Passed && !occurrence.AbsenceOnly {
				score.Faults = append(score.Faults, fmt.Sprintf("%s:%d proves %s, which %s:%d says %s does not owe", occurrence.Marker.Path, occurrence.Marker.Line, exemption.Item, exemption.File, exemption.Line, language))
			}
		}
	}
	for _, key := range debt.Owed {
		held := byKey[key]
		witnessed := slices.ContainsFunc(held, func(each Occurrence) bool { return each.Status == Passed && !each.AbsenceOnly })
		failed := slices.ContainsFunc(held, func(each Occurrence) bool { return each.Status != Passed })
		switch {
		case failed:
		case witnessed:
			score.Proven++
		default:
			score.Missing = append(score.Missing, key)
		}
	}
	if score.Owed == 0 {
		score.Faults = append(score.Faults, "nothing is owed, so nothing is proven")
	}
	slices.SortFunc(score.Wrong, func(a, b Occurrence) int {
		return cmp.Or(cmp.Compare(a.Marker.Key, b.Marker.Key), cmp.Compare(a.Marker.Path, b.Marker.Path), cmp.Compare(a.Marker.Line, b.Marker.Line))
	})
	slices.Sort(score.Faults)

	return score
}

func (s Score) Holds() bool {
	return !s.Incomplete && len(s.Faults) == 0 && s.Owed > 0 && s.Proven == s.Owed
}

func (s Score) Report() []string {
	if s.Incomplete {
		return []string{fmt.Sprintf("%s: incomplete execution", s.Language)}
	}
	lines := []string{fmt.Sprintf("%s: keys %d/%d (%d%%), markers %d/%d", s.Language, s.Proven, s.Owed, s.Proven*100/max(s.Owed, 1), s.Passed, s.Total)}
	for _, fault := range s.Faults {
		lines = append(lines, fmt.Sprintf("%s: %s", s.Language, fault))
	}
	for _, occurrence := range s.Wrong {
		lines = append(lines, fmt.Sprintf("%s: %s", s.Language, occurrence))
	}
	for _, key := range s.Missing {
		lines = append(lines, fmt.Sprintf("%s: %s: no marker proves it", s.Language, key))
	}

	return lines
}
