package engine

import (
	"slices"
	"sort"
)

const (
	TwinMinShared    = 4
	TwinMaxShare     = 0.01
	twinMinCoreVerbs = 2
	twinMaxExtra     = 2
	twinMaxDiverge   = 1
	twinCallDepth    = 2
)

// ReachedUnit is one unit of a population, a function or a method, and the rare resources it reaches.
type ReachedUnit struct {
	Key       string
	Match     Match
	Resources []string
}

// Divergence is two units found to be one job done twice, the poorer one doing less: what it misses.
type Divergence struct {
	Poorer  string
	Richer  string
	Missing []string
}

// TwinJudge is what a language answers about its units for the twin reading.
type TwinJudge interface {
	IsType(resource string) bool
	ArePolymorphicSiblings(poorer, richer ReachedUnit) bool
	ResultsAreIncomparable(poorer, richer ReachedUnit) bool
	CallersOf(key string) []string
}

func DivergentTwins(judge TwinJudge, units []ReachedUnit) []Divergence {
	units = slices.DeleteFunc(slices.Clone(units), func(unit ReachedUnit) bool { return unit.Match.IsTest() })
	byKey := map[string]ReachedUnit{}
	for _, unit := range units {
		byKey[unit.Key] = unit
	}
	reading := &twinReading{judge: judge, callers: map[string][]string{}}
	var divergences []Divergence
	claimed := map[string]bool{}
	for _, pair := range reachPairs(units, TwinMinShared) {
		divergence, ok := reading.divergenceOf(byKey[pair[0]], byKey[pair[1]])
		if ok && !claimed[divergence.Poorer] {
			claimed[divergence.Poorer] = true
			divergences = append(divergences, divergence)
		}
	}

	return divergences
}

func PairOf(divergences []Divergence, key string) string {
	for _, divergence := range divergences {
		if divergence.Poorer == key || divergence.Richer == key {
			return min(divergence.Poorer, divergence.Richer) + "|" + max(divergence.Poorer, divergence.Richer)
		}
	}

	return ""
}

func reachPairs(units []ReachedUnit, minimum int) [][2]string {
	var holders []string
	byResource := map[string][]string{}
	for _, unit := range units {
		for _, resource := range unit.Resources {
			if _, seen := byResource[resource]; !seen {
				holders = append(holders, resource)
			}
			byResource[resource] = append(byResource[resource], unit.Key)
		}
	}
	var order [][2]string
	shared := map[[2]string]int{}
	for _, resource := range holders {
		for _, pair := range PairsAmong(byResource[resource]) {
			if _, seen := shared[pair]; !seen {
				order = append(order, pair)
			}
			shared[pair]++
		}
	}
	sort.SliceStable(order, func(a, b int) bool { return shared[order[a]] > shared[order[b]] })

	return slices.DeleteFunc(order, func(pair [2]string) bool { return shared[pair] < minimum })
}

func PairsAmong(names []string) [][2]string {
	var pairs [][2]string
	for at, one := range names {
		for _, other := range names[at+1:] {
			pairs = append(pairs, [2]string{min(one, other), max(one, other)})
		}
	}

	return pairs
}

// twinReading holds the callers the judge named, so each is asked once.
type twinReading struct {
	judge   TwinJudge
	callers map[string][]string
}

func (r *twinReading) divergenceOf(first, second ReachedUnit) (Divergence, bool) {
	poorer, richer := first, second
	if len(first.Resources) > len(second.Resources) {
		poorer, richer = second, first
	}
	core, missing := shared(poorer.Resources, richer.Resources), without(richer.Resources, poorer.Resources)
	switch {
	case !r.isOneJob(core), len(missing) == 0 || len(missing) > twinMaxExtra:
		return Divergence{}, false
	case len(without(poorer.Resources, richer.Resources)) > twinMaxDiverge, len(r.verbsIn(missing)) == 0:
		return Divergence{}, false
	case r.areAlternatives(poorer, richer):
		return Divergence{}, false
	}

	return Divergence{Poorer: poorer.Key, Richer: richer.Key, Missing: missing}, true
}

func (r *twinReading) isOneJob(core []string) bool {
	return len(core) >= TwinMinShared && len(r.verbsIn(core)) >= twinMinCoreVerbs
}

func (r *twinReading) verbsIn(resources []string) []string {
	return slices.DeleteFunc(slices.Clone(resources), r.judge.IsType)
}

func (r *twinReading) areAlternatives(poorer, richer ReachedUnit) bool {
	return r.judge.ArePolymorphicSiblings(poorer, richer) || r.judge.ResultsAreIncomparable(poorer, richer) ||
		r.routesThrough(poorer.Key, richer.Key) || r.routesThrough(richer.Key, poorer.Key) || r.shareACaller(poorer, richer)
}

func (r *twinReading) routesThrough(caller, callee string) bool {
	frontier, seen := []string{callee}, map[string]bool{callee: true}
	for range twinCallDepth {
		var next []string
		for _, key := range frontier {
			for _, calling := range r.callersOf(key) {
				if calling == caller {
					return true
				}
				if seen[calling] {
					continue
				}
				seen[calling] = true
				next = append(next, calling)
			}
		}
		frontier = next
	}

	return false
}

func (r *twinReading) shareACaller(poorer, richer ReachedUnit) bool {
	return len(shared(r.callersOf(poorer.Key), r.callersOf(richer.Key))) > 0
}

func (r *twinReading) callersOf(key string) []string {
	if held, ok := r.callers[key]; ok {
		return held
	}
	r.callers[key] = r.judge.CallersOf(key)

	return r.callers[key]
}

func shared(one, other []string) []string {
	return slices.DeleteFunc(slices.Clone(one), func(item string) bool { return !slices.Contains(other, item) })
}

func without(one, other []string) []string {
	return slices.DeleteFunc(slices.Clone(one), func(item string) bool { return slices.Contains(other, item) })
}
