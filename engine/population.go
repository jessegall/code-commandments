package engine

import (
	"cmp"
	"math"
	"slices"
)

// ResourcePopulation is what one population of units reaches, and how rare each resource is within it.
type ResourcePopulation struct {
	reach   map[string]map[string]bool
	holders map[string]int
}

func Counting(reach map[string]map[string]bool) ResourcePopulation {
	holders := map[string]int{}
	for _, resources := range reach {
		for resource := range resources {
			holders[resource]++
		}
	}

	return ResourcePopulation{reach: reach, holders: holders}
}

func (p ResourcePopulation) Of(unit string) map[string]bool {
	return p.reach[unit]
}

func (p ResourcePopulation) HoldersOf(resource string) int {
	return p.holders[resource]
}

func (p ResourcePopulation) WeightOf(resource string) float64 {
	return math.Log(float64(max(2, len(p.reach))) / float64(max(1, p.HoldersOf(resource))))
}

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
