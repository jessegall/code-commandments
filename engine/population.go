package engine

import (
	"cmp"
	"math"
	"slices"
)

// ResourcePopulation is what one population of units reaches, and how rare each resource is within it. A
// population is a granularity, every class or every method, and rarity only means anything against the units it
// was counted over, so the two travel together.
type ResourcePopulation struct {
	reach   map[string]map[string]bool
	holders map[string]int
}

// Counting is the population the reach makes, each resource counted by how many units reach it.
func Counting(reach map[string]map[string]bool) ResourcePopulation {
	holders := map[string]int{}
	for _, resources := range reach {
		for resource := range resources {
			holders[resource]++
		}
	}

	return ResourcePopulation{reach: reach, holders: holders}
}

// Of is every resource the unit reaches.
func (p ResourcePopulation) Of(unit string) map[string]bool {
	return p.reach[unit]
}

// HoldersOf is how many units reach the resource.
func (p ResourcePopulation) HoldersOf(resource string) int {
	return p.holders[resource]
}

// WeightOf is how much the resource tells: its inverse frequency here. A verb three units reach all but names a
// mechanism; one half of them reach names nothing.
func (p ResourcePopulation) WeightOf(resource string) float64 {
	return math.Log(float64(max(2, len(p.reach))) / float64(max(1, p.HoldersOf(resource))))
}

// RareOf is the reach of the unit worth comparing: resources held by at most maxShare of the population, rarest
// first, then by name. Anything more widespread is the program's background. Never below two holders: a mechanism
// only exists by recurring, and a small program has no background to speak of.
func (p ResourcePopulation) RareOf(unit string, maxShare float64) []string {
	ceiling := max(2, int(math.Ceil(maxShare*float64(len(p.reach)))))
	var rare []string
	for resource := range p.Of(unit) {
		if p.HoldersOf(resource) <= ceiling {
			rare = append(rare, resource)
		}
	}
	slices.SortFunc(rare, func(a, b string) int {
		return cmp.Or(cmp.Compare(p.HoldersOf(a), p.HoldersOf(b)), cmp.Compare(a, b))
	})

	return rare
}
