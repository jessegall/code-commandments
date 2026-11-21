package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
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

func ResolvedTwinPairs(codebase *Codebase) TwinPairs {
	return Analysis(codebase, "resolved-twin-pairs", resolveTwins)
}

func resolveTwins(codebase *Codebase) TwinPairs {
	matches := map[string]Match{}
	reach := map[string]map[string]bool{}
	functions := codebase.Reporting().WhereFunction().Reject(Match.IsTest).Get()
	for _, function := range functions {
		key := function.Node().Symbol
		if key == "" || !function.Child("body").Exists() {
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
			resources["call:"+methodOf(node.Node().Target, target)] = true
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
	one, other := poorer.Match.EnclosingType(), richer.Match.EnclosingType()
	if !one.Exists() || !other.Exists() || one.Node().Symbol == other.Node().Symbol {
		return false
	}
	types, known := poorer.Match.Types()
	if !known {
		return false
	}
	shared := types.Contracts(other.Node().Symbol)

	return slices.ContainsFunc(types.Contracts(one.Node().Symbol), func(contract string) bool { return slices.Contains(shared, contract) })
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

// methodOf is the method a call reaches, its overloads one: the declaring type and the name, where the target names
// both, so a call of AppendLine() and one of AppendLine(string) take the same step.
func methodOf(target *contract.Target, symbol string) string {
	if target == nil || target.Type == "" || target.Name == "" {
		return symbol
	}

	return target.Type + "." + target.Name
}
