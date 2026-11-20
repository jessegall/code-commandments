package engine

import (
	"slices"
	"strings"
)

const (
	mechanismMinLines  = 25
	mechanismMaxShare  = 0.01
	mechanismMinShared = 4
	mechanismMinVerbs  = 2
	mechanismMinWeight = 30.0
)

// MechanismFacts describes the resources a codebase's classes reach.
type MechanismFacts struct {
	Classes    ResourcePopulation
	IsType     func(string) bool
	IsTerminal func(string) bool
	Namespace  func(Match) string
}

// MechanismGroups contains the findings and their shared resource keys.
type MechanismGroups struct {
	Findings []Match
	Keys     map[string]string
}

// mechanismUnit is one class and the rare outside resources it reaches.
type mechanismUnit struct {
	match Match
	verbs []string
}

func MechanismsOf(codebase *Codebase) MechanismGroups {
	return Analysis(codebase, "duplicated-mechanisms", readMechanisms)
}

func readMechanisms(codebase *Codebase) MechanismGroups {
	classes := codebase.Reporting().WhereTypeDeclaration().Where(func(class Match) bool {
		return class.TypeKind() == "class" || class.TypeKind() == "struct"
	}).Get()
	methods := mechanismMethods(codebase, classes)
	facts := mechanismFacts(codebase, classes, methods)
	units := map[string]mechanismUnit{}
	var order []string
	for _, class := range classes {
		if mechanismLines(class, methods[class.Identity()]) < mechanismMinLines {
			continue
		}
		rare := facts.Classes.RareOf(class.Identity(), mechanismMaxShare)
		verbs := slices.DeleteFunc(rare, func(resource string) bool { return !facts.IsTerminal(resource) })
		if len(verbs) >= mechanismMinShared {
			units[class.Location()] = mechanismUnit{match: class, verbs: verbs}
			order = append(order, class.Location())
		}
	}
	found := MechanismGroups{Keys: map[string]string{}}
	for _, cluster := range clustered(facts, units, order) {
		common := units[cluster[0]].verbs
		for _, location := range cluster[1:] {
			common = shared(common, units[location].verbs)
		}
		key := strings.Join(common, "|")
		for _, location := range cluster {
			found.Keys[location] = key
			found.Findings = append(found.Findings, units[location].match)
		}
	}

	return found
}

func mechanismMethods(codebase *Codebase, classes []Match) map[string][]Match {
	if len(classes) == 0 || classes[0].grammar().MethodOwner == nil {
		return nil
	}
	return methodsByOwner(codebase)
}

func mechanismLines(class Match, methods []Match) int {
	lines := 0
	for _, part := range append([]Match{class}, methods...) {
		if span, err := part.Span(); err == nil {
			lines += strings.Count(span.Text(), "\n") + 1
		}
	}

	return lines
}

func mechanismFacts(codebase *Codebase, classes []Match, methods map[string][]Match) MechanismFacts {
	for _, class := range classes {
		if read := class.grammar().MechanismFacts; read != nil {
			return read(codebase)
		}
	}
	reach := map[string]map[string]bool{}
	for _, class := range classes {
		nodes := class.Descendants()
		for _, method := range methods[class.Identity()] {
			nodes = append(nodes, method.OwnDescendants()...)
		}
		resources := reachIn(class, nodes)
		for _, edge := range nodes {
			if name := edge.Refers(); name != "" && name != class.Identity() {
				resources[name] = true
			}
		}
		reach[class.Identity()] = resources
	}

	return MechanismFacts{
		Classes: Counting(reach),
		IsType:  func(resource string) bool { return !strings.HasPrefix(resource, "call:") },
		IsTerminal: func(resource string) bool {
			symbol := strings.TrimPrefix(strings.TrimPrefix(resource, "call:"), typeResourcePrefix)
			return len(codebase.Declarations(symbol)) == 0
		},
		Namespace: Match.Namespace,
	}
}

func mechanismPairs(facts MechanismFacts, units map[string]mechanismUnit, order []string) [][2]string {
	reached := make([]ReachedUnit, 0, len(order))
	for _, location := range order {
		reached = append(reached, ReachedUnit{Key: location, Resources: units[location].verbs})
	}

	return slices.DeleteFunc(reachPairs(reached, mechanismMinShared), func(pair [2]string) bool {
		return !arePeers(facts, units[pair[0]].match, units[pair[1]].match)
	})
}

func arePeers(facts MechanismFacts, one, other Match) bool {
	if facts.Namespace(one) == facts.Namespace(other) {
		return false
	}

	return !facts.Classes.Of(one.Identity())[other.Identity()] && !facts.Classes.Of(other.Identity())[one.Identity()]
}

func clustered(facts MechanismFacts, units map[string]mechanismUnit, order []string) [][]string {
	taken := map[string]bool{}
	var clusters [][]string
	for _, pair := range mechanismPairs(facts, units, order) {
		one, other := pair[0], pair[1]
		if taken[one] || taken[other] {
			continue
		}
		common := shared(units[one].verbs, units[other].verbs)
		if !isMechanism(facts, common) {
			continue
		}
		cluster := []string{one, other}
		taken[one], taken[other] = true, true
		for _, location := range order {
			if taken[location] {
				continue
			}
			joined := shared(common, units[location].verbs)
			if !isMechanism(facts, joined) || slices.ContainsFunc(cluster, func(member string) bool {
				return !arePeers(facts, units[member].match, units[location].match)
			}) {
				continue
			}
			cluster = append(cluster, location)
			common = joined
			taken[location] = true
		}
		clusters = append(clusters, cluster)
	}

	return clusters
}

func isMechanism(facts MechanismFacts, shared []string) bool {
	if len(shared) < mechanismMinShared {
		return false
	}
	weight, verbs := 0.0, 0
	for _, resource := range shared {
		if facts.IsType(resource) {
			continue
		}
		verbs++
		weight += facts.Classes.WeightOf(resource)
	}

	return verbs >= mechanismMinVerbs && weight >= mechanismMinWeight
}
