package engine

import (
	"slices"
	"strings"
)

const typeResourcePrefix = "type:"

// TwinPairs holds a population of functions and the divergent pairs among them.
type TwinPairs struct {
	Units []ReachedUnit
	Pairs []Divergence
}

func (pairs TwinPairs) Findings() []Match {
	byKey := make(map[string]Match, len(pairs.Units))
	for _, unit := range pairs.Units {
		byKey[unit.Key] = unit.Match
	}
	var findings []Match
	seen := map[string]bool{}
	for _, pair := range pairs.Pairs {
		for _, key := range []string{pair.Poorer, pair.Richer} {
			if seen[key] {
				continue
			}
			seen[key] = true
			if match := byKey[key]; match.Exists() {
				findings = append(findings, match)
			}
		}
	}

	return findings
}

func (pairs TwinPairs) Key(match Match) (string, bool) {
	for _, unit := range pairs.Units {
		if unit.Match.Node() == match.Node() {
			key := PairOf(pairs.Pairs, unit.Key)

			return key, key != ""
		}
	}

	return "", false
}

func TwinsOf(codebase *Codebase) TwinPairs {
	for _, file := range codebase.Files() {
		grammar := spokenIn(file.Language()).grammar
		if grammar != nil && grammar.TwinPairs != nil {
			return grammar.TwinPairs(codebase)
		}
	}

	return ResolvedTwinPairs(codebase)
}

func (m Match) TwinGroup() (string, bool) {
	if m.file == nil {
		return "", false
	}
	codebase := m.file.Codebase()
	if read := m.grammar().TwinPairs; read != nil {
		return read(codebase).Key(m)
	}

	return ResolvedTwinPairs(codebase.Of(m.file.Language())).Key(m)
}

func ResolvedTwinPairs(codebase *Codebase) TwinPairs {
	return Analysis(codebase, "resolved-twin-pairs", resolveTwins)
}

func resolveTwins(codebase *Codebase) TwinPairs {
	matches := map[string]Match{}
	reach := map[string]map[string]bool{}
	functions := codebase.Reporting().WhereFunction().Reject(Match.IsTest).Get()
	for _, function := range functions {
		key := function.Node().Symbol
		if key == "" || len(function.Body()) == 0 {
			continue
		}
		matches[key], reach[key] = function, reachedSymbols(function)
	}
	population := Counting(reach)
	var units []ReachedUnit
	for _, function := range functions {
		key := function.Node().Symbol
		if resources := population.RareOf(key, TwinMaxShare); len(resources) >= TwinMinShared {
			units = append(units, ReachedUnit{Key: key, Match: function, Resources: resources})
		}
	}

	return TwinPairs{Units: units, Pairs: DivergentTwins(resolvedTwinJudge{matches}, units)}
}

func reachedSymbols(unit Match) map[string]bool {
	return reachIn(unit, unit.OwnDescendants())
}

func reachIn(unit Match, nodes []Match) map[string]bool {
	resources := map[string]bool{}
	for _, node := range nodes {
		if node.Is(Construction) && node.Node().Target != nil && node.Node().Target.Type != "" {
			resources[typeResourcePrefix+node.Node().Target.Type] = true
			continue
		}
		if target := node.TargetSymbol(); node.Is(Call) && target != "" && target != unit.Identity() {
			resources["call:"+target] = true
		}
	}

	return resources
}

// resolvedTwinJudge reads the call targets and type relationships carried by declarations.
type resolvedTwinJudge struct {
	matches map[string]Match
}

func (resolvedTwinJudge) IsType(resource string) bool {
	return strings.HasPrefix(resource, typeResourcePrefix)
}

func (resolvedTwinJudge) ArePolymorphicSiblings(poorer, richer ReachedUnit) bool {
	if poorer.Match.Name() != richer.Match.Name() {
		return false
	}
	if poorer.Match.IsOverride() && richer.Match.IsOverride() {
		return true
	}
	if owner := poorer.Match.grammar().MethodOwner; owner != nil {
		one, other := owner(poorer.Match), owner(richer.Match)
		return one != "" && other != "" && one != other
	}

	return false
}

func (resolvedTwinJudge) ResultsAreIncomparable(poorer, richer ReachedUnit) bool {
	one, other := poorer.Match.ReturnType().Named(), richer.Match.ReturnType().Named()
	if one == "" || other == "" || one == other {
		return false
	}
	types, known := poorer.Match.Types()
	if !known {
		return false
	}
	_, hasOne := types.Declaration(one)
	_, hasOther := types.Declaration(other)

	return hasOne && hasOther && !IsA(types, one, other) && !IsA(types, other, one)
}

func (judge resolvedTwinJudge) CallersOf(key string) []string {
	var callers []string
	for _, referred := range judge.matches[key].Referrers() {
		caller := referred.EnclosingFunction()
		if caller.Exists() && !slices.Contains(callers, caller.Identity()) {
			callers = append(callers, caller.Identity())
		}
	}

	return callers
}
