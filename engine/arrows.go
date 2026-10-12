package engine

import (
	"cmp"
	"slices"
	"strings"
)

// DependencyArrow is one reference from a part of a codebase to another, where it is written.
type DependencyArrow struct {
	At   Match
	From string
	To   string
}

// DependencyArrows is the references between the parts of a codebase, in the order the files are written: which
// part uses which, and the pairs of parts that use each other. The one reading of it every engine shares.
type DependencyArrows []DependencyArrow

// Nests says whether the inner part sits under the outer one, the parts of a name separated so: a sub-namespace
// is INSIDE its parent and a sub-package inside the folder above it, so a reference between the two crosses no
// boundary and is no dependency between two parts of a codebase.
func Nests(outer, inner, separator string) bool {
	return strings.HasPrefix(inner, outer+separator)
}

// Independent is the arrows between parts neither of which contains the other: the only ones that can be a
// dependency to judge, since an arrow into a part's own sub-part stays inside it.
func (a DependencyArrows) Independent(separator string) DependencyArrows {
	var independent DependencyArrows
	for _, arrow := range a {
		if !Nests(arrow.From, arrow.To, separator) && !Nests(arrow.To, arrow.From, separator) {
			independent = append(independent, arrow)
		}
	}

	return independent
}

// Has says whether some reference in from reaches to.
func (a DependencyArrows) Has(from, to string) bool {
	return slices.ContainsFunc(a, func(arrow DependencyArrow) bool { return arrow.From == from && arrow.To == to })
}

// References is each part the codebase references another from, with the parts it references, in first-written
// order.
func (a DependencyArrows) References() map[string][]string {
	references := map[string][]string{}
	for _, arrow := range a {
		if !slices.Contains(references[arrow.From], arrow.To) {
			references[arrow.From] = append(references[arrow.From], arrow.To)
		}
	}

	return references
}

// Pair is the two parts a reference joins: the part it is written in and the part it reaches.
type Pair struct {
	From string
	To   string
}

// ArrowCounts is references counted by the parts they join, without where they are written: the references of
// the parts of a program not held, beside the arrows of the part that is.
type ArrowCounts map[Pair]int

// ClosingAMutualPair is the references of the direction worth cutting in every pair of parts that use each
// other: the thinner of the two, the one with fewer references, ties broken on the name so a codebase always
// yields the same answer. One per file and part it reaches, each where it is written.
func (a DependencyArrows) ClosingAMutualPair() []Match {
	return a.ClosingAMutualPairBeside(nil)
}

// ClosingAMutualPairBeside is ClosingAMutualPair with the references elsewhere in the program counted beside
// these: only these are reported, since only these are held where they are written.
func (a DependencyArrows) ClosingAMutualPairBeside(elsewhere ArrowCounts) []Match {
	type pair struct{ from, to string }
	count := map[pair]int{}
	for _, arrow := range a {
		count[pair{arrow.From, arrow.To}]++
	}
	for held, references := range elsewhere {
		count[pair{held.From, held.To}] += references
	}
	type place struct{ file, to string }
	seen := map[place]bool{}
	var closing []Match
	for _, arrow := range a {
		back := count[pair{arrow.To, arrow.From}]
		thinner := cmp.Or(cmp.Compare(count[pair{arrow.From, arrow.To}], back), cmp.Compare(arrow.From, arrow.To)) <= 0
		if back == 0 || !thinner || seen[place{arrow.At.File(), arrow.To}] {
			continue
		}
		seen[place{arrow.At.File(), arrow.To}] = true
		closing = append(closing, arrow.At)
	}

	return closing
}
