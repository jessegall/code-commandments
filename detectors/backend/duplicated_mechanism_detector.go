package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DuplicatedMechanismDetector finds classes in different namespaces, neither knowing the other, that reach the same
// rare outside verbs: one mechanism built twice.
type DuplicatedMechanismDetector struct{}

func init() { detectors.Register(catalog.Backend, DuplicatedMechanismDetector{}) }

const (
	mechanismMinLines  = 25
	mechanismMaxShare  = 0.01
	mechanismMinShared = 4
	mechanismMinVerbs  = 2
	mechanismMinWeight = 30.0
)

// Sin is the sin the detector finds.
func (DuplicatedMechanismDetector) Sin() sins.Sin { return backendsins.DuplicatedMechanism{} }

// Unpublished keeps the detector out of every catalog while it is calibrated.
func (DuplicatedMechanismDetector) Unpublished() {}

// GroupKey is the verbs the class's cluster shares.
func (DuplicatedMechanismDetector) GroupKey(finding engine.Match, codebase *engine.Codebase) string {
	return mechanismsOf(codebase).keys[finding.Location()]
}

// Find is every class of a cluster of peers sharing a mechanism's worth of rare outside verbs.
func (DuplicatedMechanismDetector) Find(codebase *engine.Codebase) []engine.Match {
	return mechanismsOf(codebase).findings
}

type mechanisms struct {
	findings []engine.Match
	keys     map[string]string
}

type mechanismUnit struct {
	match engine.Match
	verbs []string
}

func mechanismsOf(codebase *engine.Codebase) mechanisms {
	return engine.Analysis(codebase, "backend/duplicated-mechanisms", func(codebase *engine.Codebase) mechanisms {
		reach := php.ReachOf(codebase)
		units := map[string]mechanismUnit{}
		var order []string
		for _, class := range php.In(codebase).WhereKind("Stmt_Class").Get() {
			if (php.Node{Match: class}).LineCount() < mechanismMinLines {
				continue
			}
			var verbs []string
			for _, resource := range reach.Classes.RareOf(class.Node().Symbol, mechanismMaxShare) {
				if reach.IsTerminal(resource) {
					verbs = append(verbs, resource)
				}
			}
			if len(verbs) >= mechanismMinShared {
				units[class.Location()] = mechanismUnit{match: class, verbs: verbs}
				order = append(order, class.Location())
			}
		}
		found := mechanisms{keys: map[string]string{}}
		for _, cluster := range clustered(codebase, units, order) {
			shared := units[cluster[0]].verbs
			for _, location := range cluster[1:] {
				shared = intersect(shared, units[location].verbs)
			}
			for _, location := range cluster {
				found.keys[location] = strings.Join(shared, "|")
				found.findings = append(found.findings, units[location].match)
			}
		}

		return found
	})
}

func intersect(these, those []string) []string {
	var both []string
	for _, each := range these {
		if slices.Contains(those, each) {
			both = append(both, each)
		}
	}

	return both
}

// mechanismPairs is every pair of peer units sharing enough verbs, most shared first.
func mechanismPairs(codebase *engine.Codebase, units map[string]mechanismUnit, order []string) [][2]string {
	holders := map[string][]string{}
	var verbs []string
	for _, location := range order {
		for _, verb := range units[location].verbs {
			if holders[verb] == nil {
				verbs = append(verbs, verb)
			}
			holders[verb] = append(holders[verb], location)
		}
	}
	overlap := map[[2]string]int{}
	var pairs [][2]string
	for _, verb := range verbs {
		locations := holders[verb]
		for at, one := range locations {
			for _, other := range locations[at+1:] {
				pair := [2]string{min(one, other), max(one, other)}
				if overlap[pair] == 0 {
					pairs = append(pairs, pair)
				}
				overlap[pair]++
			}
		}
	}
	pairs = slices.DeleteFunc(pairs, func(pair [2]string) bool { return overlap[pair] < mechanismMinShared })
	slices.SortStableFunc(pairs, func(a, b [2]string) int { return overlap[b] - overlap[a] })

	return slices.DeleteFunc(pairs, func(pair [2]string) bool {
		return !arePeers(codebase, units[pair[0]].match, units[pair[1]].match)
	})
}

// arePeers says whether two classes sit in different namespaces and neither names the other.
func arePeers(codebase *engine.Codebase, one, other engine.Match) bool {
	if (php.Node{Match: one}).NamespaceName() == (php.Node{Match: other}).NamespaceName() {
		return false
	}
	classes := php.ReachOf(codebase).Classes

	return !classes.Of(one.Node().Symbol)[other.Node().Symbol] && !classes.Of(other.Node().Symbol)[one.Node().Symbol]
}

func clustered(codebase *engine.Codebase, units map[string]mechanismUnit, order []string) [][]string {
	taken := map[string]bool{}
	var clusters [][]string
	for _, pair := range mechanismPairs(codebase, units, order) {
		one, other := pair[0], pair[1]
		if taken[one] || taken[other] {
			continue
		}
		shared := intersect(units[one].verbs, units[other].verbs)
		if !isMechanism(codebase, shared) {
			continue
		}
		cluster := []string{one, other}
		taken[one], taken[other] = true, true
		for _, location := range order {
			if taken[location] {
				continue
			}
			joined := intersect(shared, units[location].verbs)
			if !isMechanism(codebase, joined) || slices.ContainsFunc(cluster, func(member string) bool {
				return !arePeers(codebase, units[member].match, units[location].match)
			}) {
				continue
			}
			cluster = append(cluster, location)
			shared = joined
			taken[location] = true
		}
		clusters = append(clusters, cluster)
	}

	return clusters
}

// isMechanism says whether shared resources amount to a mechanism: enough of them, verbs among them, and weight.
func isMechanism(codebase *engine.Codebase, shared []string) bool {
	reach := php.ReachOf(codebase)
	if len(shared) < mechanismMinShared {
		return false
	}
	weight, verbs := 0.0, 0
	for _, resource := range shared {
		if !reach.IsType(resource) {
			verbs++
			weight += reach.Classes.WeightOf(resource)
		}
	}

	return verbs >= mechanismMinVerbs && weight >= mechanismMinWeight
}
