package engine

import (
	"slices"
	"sort"
)

// Twin thresholds: when two paths are one job done twice with one doing less of it.
const (
	// TwinMinShared is how many resources two paths must share before they are worth comparing at all.
	TwinMinShared = 4
	// TwinMaxShare is what share of the population may reach a resource before it stops telling anything: the
	// verbs that name a mechanism sit at or under half a percent of units, the idioms every program repeats well
	// above one.
	TwinMaxShare = 0.01
	// twinMinCoreVerbs is how many shared resources must be verbs: a core of verbs says the two do one job, where a
	// core of types only says they work on one subject.
	twinMinCoreVerbs = 2
	// twinMaxExtra is how many steps the richer path may have on top before the two are simply different.
	twinMaxExtra = 2
	// twinMaxDiverge is how many resources the poorer path may have that the richer lacks.
	twinMaxDiverge = 1
	// twinCallDepth is how far a call is followed when asking whether one path routes through the other.
	twinCallDepth = 2
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
	// IsType says whether a resource is a type the units work on rather than a verb they perform.
	IsType(resource string) bool
	// ArePolymorphicSiblings says whether the two answer one contract both inherit.
	ArePolymorphicSiblings(poorer, richer ReachedUnit) bool
	// ResultsAreIncomparable says whether the two declare results that cannot be one value.
	ResultsAreIncomparable(poorer, richer ReachedUnit) bool
	// CallersOf is the keys of the units that call the unit.
	CallersOf(unit ReachedUnit) []string
}

// DivergentTwins is each pair of the units found to be one job done twice where one does less, one finding per
// poorer unit, the strongest pair claiming it.
func DivergentTwins(judge TwinJudge, units []ReachedUnit) []Divergence {
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

// PairOf is the pair the key belongs to among the divergences, named alike from either side; empty for none.
func PairOf(divergences []Divergence, key string) string {
	for _, divergence := range divergences {
		if divergence.Poorer == key || divergence.Richer == key {
			return min(divergence.Poorer, divergence.Richer) + "|" + max(divergence.Poorer, divergence.Richer)
		}
	}

	return ""
}

// reachPairs is every pair of units sharing at least minimum resources, the most shared first.
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
		keys := byResource[resource]
		for at, one := range keys {
			for _, other := range keys[at+1:] {
				pair := [2]string{min(one, other), max(one, other)}
				if _, seen := shared[pair]; !seen {
					order = append(order, pair)
				}
				shared[pair]++
			}
		}
	}
	sort.SliceStable(order, func(a, b int) bool { return shared[order[a]] > shared[order[b]] })

	return slices.DeleteFunc(order, func(pair [2]string) bool { return shared[pair] < minimum })
}

// twinReading holds the callers the judge named, so each is asked once.
type twinReading struct {
	judge   TwinJudge
	callers map[string][]string
}

// divergenceOf is the divergence of the two, when one is the other doing strictly less.
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

// isOneJob says whether the shared core is carried by verbs rather than by the subject both handle.
func (r *twinReading) isOneJob(core []string) bool {
	return len(core) >= TwinMinShared && len(r.verbsIn(core)) >= twinMinCoreVerbs
}

func (r *twinReading) verbsIn(resources []string) []string {
	return slices.DeleteFunc(slices.Clone(resources), r.judge.IsType)
}

// areAlternatives says whether the two are anything other than independent implementations of one job: siblings
// under one contract, results that cannot be one value, one built on the other, or alternatives a third picks
// between.
func (r *twinReading) areAlternatives(poorer, richer ReachedUnit) bool {
	return r.judge.ArePolymorphicSiblings(poorer, richer) || r.judge.ResultsAreIncomparable(poorer, richer) ||
		r.routesThrough(poorer.Key, richer) || r.routesThrough(richer.Key, poorer) || r.shareACaller(poorer, richer)
}

// routesThrough says whether the caller reaches the callee by calling it, directly or through a helper.
func (r *twinReading) routesThrough(caller string, callee ReachedUnit) bool {
	frontier := map[string]bool{caller: true}
	for range twinCallDepth {
		for _, calling := range r.callersOf(callee) {
			if frontier[calling] {
				return true
			}
		}
		next := map[string]bool{}
		for key := range frontier {
			for _, calling := range r.callers[key] {
				next[calling] = true
			}
		}
		frontier = next
	}

	return false
}

// shareACaller says whether a third unit calls both.
func (r *twinReading) shareACaller(poorer, richer ReachedUnit) bool {
	return len(shared(r.callersOf(poorer), r.callersOf(richer))) > 0
}

func (r *twinReading) callersOf(unit ReachedUnit) []string {
	if held, ok := r.callers[unit.Key]; ok {
		return held
	}
	r.callers[unit.Key] = r.judge.CallersOf(unit)

	return r.callers[unit.Key]
}

// shared is what of one is also in other, in one's order.
func shared(one, other []string) []string {
	return slices.DeleteFunc(slices.Clone(one), func(item string) bool { return !slices.Contains(other, item) })
}

// without is what of one is not in other, in one's order.
func without(one, other []string) []string {
	return slices.DeleteFunc(slices.Clone(one), func(item string) bool { return slices.Contains(other, item) })
}
